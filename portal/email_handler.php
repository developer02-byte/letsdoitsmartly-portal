<?php
/**
 * Email Handler
 * Handles email account CRUD operations with Google Workspace sync
 */

// Temporary error display - REMOVE AFTER DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/google_functions.php';
require_once 'includes/password_validation.php';

requireLogin();

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid security token']);
        }
        header('Location: emails.php?error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addEmail();
        break;
    case 'edit':
        editEmail();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    case 'delete':
        deleteEmail();
        break;
    case 'delete_confirmed':
        deleteEmailConfirmed();
        break;
    case 'reset_password':
        resetPassword();
        break;
    default:
        header('Location: emails.php');
        exit;
}

/**
 * Check if user can access a domain
 */
function canAccessDomain($domain_id) {
    global $conn;

    if (isSuperAdmin()) {
        return true;
    }

    $allowed = getAllowedDomainIds();
    return in_array($domain_id, $allowed ?: []);
}

/**
 * Check if domain is available for operations (not removed from Google)
 * Returns error message if domain is removed, null if OK
 */
function checkDomainAvailable($domain_id) {
    global $conn;

    $check = isDomainRemovedFromGoogle($conn, $domain_id);
    if ($check['removed']) {
        $removed_date = $check['removed_at'] ? date('M j, Y', strtotime($check['removed_at'])) : 'unknown date';
        return "Domain was removed from Google Workspace on $removed_date. No operations are allowed.";
    }
    return null;
}

/**
 * Add new email account - Creates in Google first, then database
 */
function addEmail() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);
    $username = strtolower(trim($_POST['username'] ?? ''));
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'active';

    // Validate domain
    if ($domain_id <= 0) {
        header('Location: emails.php?error=Please select a domain');
        exit;
    }

    // Check domain access
    if (!canAccessDomain($domain_id)) {
        header('Location: emails.php?error=Access denied to this domain');
        exit;
    }

    // Check if domain is removed from Google
    $domain_error = checkDomainAvailable($domain_id);
    if ($domain_error) {
        header('Location: emails.php?error=' . urlencode($domain_error));
        exit;
    }

    // Validate username
    if (empty($username)) {
        header('Location: emails.php?error=Username is required');
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        header('Location: emails.php?error=Invalid username format. Use only letters, numbers, dots, hyphens, and underscores.');
        exit;
    }

    // Validate password
    if (empty($password)) {
        header('Location: emails.php?error=Password is required');
        exit;
    }

    // Build email address for validation check
    $domain_stmt_temp = $conn->prepare("SELECT domain_name FROM domains WHERE id = ?");
    $domain_stmt_temp->bind_param('i', $domain_id);
    $domain_stmt_temp->execute();
    $domain_result_temp = $domain_stmt_temp->get_result();
    $temp_email = $username . '@' . ($domain_result_temp->num_rows > 0 ? $domain_result_temp->fetch_assoc()['domain_name'] : '');

    $password_validation = validatePassword($password, $temp_email);
    if (!$password_validation['valid']) {
        header('Location: emails.php?error=' . urlencode(formatPasswordErrors($password_validation['errors'])));
        exit;
    }

    // Validate names
    if (empty($first_name) || empty($last_name)) {
        header('Location: emails.php?error=First name and last name are required');
        exit;
    }

    // Get domain name
    $domain_stmt = $conn->prepare("SELECT domain_name FROM domains WHERE id = ?");
    $domain_stmt->bind_param('i', $domain_id);
    $domain_stmt->execute();
    $domain_result = $domain_stmt->get_result();

    if ($domain_result->num_rows === 0) {
        header('Location: emails.php?error=Domain not found');
        exit;
    }

    $domain_name = $domain_result->fetch_assoc()['domain_name'];
    $email_address = $username . '@' . $domain_name;

    // Check if email already exists in database
    $check = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $check->bind_param('s', $email_address);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: emails.php?error=Email address already exists');
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'suspended'])) {
        $status = 'active';
    }

    // Create user in Google Workspace FIRST
    $google_result = createGoogleWorkspaceUser($email_address, $first_name, $last_name, $password);

    if (!$google_result['success']) {
        header('Location: emails.php?error=Google API Error: ' . urlencode($google_result['error']));
        exit;
    }

    $google_user_id = $google_result['google_user_id'] ?? null;

    // If created as suspended, update Google
    if ($status === 'suspended') {
        toggleGoogleUserStatus($email_address, true);
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO email_accounts (domain_id, email_address, first_name, last_name, status, google_user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $domain_id, $email_address, $first_name, $last_name, $status, $google_user_id);

    if ($stmt->execute()) {
        $email_id = $conn->insert_id;
        logActivity($conn, "Created email account: $email_address", $email_address, $domain_name, [
            'category' => 'email',
            'resource_type' => 'email',
            'resource_id' => $email_id,
            'changes' => [
                'action' => 'create',
                'email' => [
                    'id' => $email_id,
                    'email_address' => $email_address,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'status' => $status,
                    'domain' => $domain_name
                ]
            ]
        ]);
        header('Location: emails.php?success=added');
    } else {
        // Google user was created but DB insert failed - track for recovery
        require_once 'includes/error_recovery.php';
        recordFailedOperation($conn, [
            'operation_type' => 'create_email',
            'resource_type' => 'email',
            'resource_identifier' => $email_address,
            'google_user_id' => $google_user_id,
            'domain_id' => $domain_id,
            'operation_data' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'status' => $status
            ],
            'error_message' => $conn->error,
            'error_code' => ERR_DB_INSERT_FAILED
        ]);
        header('Location: emails.php?error=Created in Google but failed to save locally. The system will auto-recover.');
    }
    exit;
}

/**
 * Edit existing email account
 */
function editEmail() {
    global $conn;

    $email_id = intval($_POST['email_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($email_id <= 0) {
        header('Location: emails.php?error=Invalid email');
        exit;
    }

    // Get current email info (for comparison)
    $current = $conn->prepare("SELECT ea.*, d.domain_name FROM email_accounts ea
                               JOIN domains d ON ea.domain_id = d.id
                               WHERE ea.id = ?");
    $current->bind_param('i', $email_id);
    $current->execute();
    $result = $current->get_result();

    if ($result->num_rows === 0) {
        header('Location: emails.php?error=Email not found');
        exit;
    }

    $email_data = $result->fetch_assoc();
    $original_data = [
        'first_name' => $email_data['first_name'],
        'last_name' => $email_data['last_name'],
        'status' => $email_data['status']
    ];

    // Check domain access
    if (!canAccessDomain($email_data['domain_id'])) {
        header('Location: emails.php?error=Access denied');
        exit;
    }

    // Check if domain is removed from Google
    $domain_error = checkDomainAvailable($email_data['domain_id']);
    if ($domain_error) {
        header('Location: emails.php?error=' . urlencode($domain_error));
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'suspended'])) {
        $status = 'active';
    }

    // If status changed, update in Google
    if ($status !== $email_data['status']) {
        $suspend = ($status === 'suspended');
        $google_result = toggleGoogleUserStatus($email_data['email_address'], $suspend);

        if (!$google_result['success']) {
            header('Location: emails.php?error=Google API Error: ' . urlencode($google_result['error']));
            exit;
        }
    }

    // Update in database
    $stmt = $conn->prepare("UPDATE email_accounts SET first_name = ?, last_name = ?, status = ? WHERE id = ?");
    $stmt->bind_param('sssi', $first_name, $last_name, $status, $email_id);

    if ($stmt->execute()) {
        // Build changes array (before/after)
        $changes = ['action' => 'update', 'before' => [], 'after' => []];
        $new_data = ['first_name' => $first_name, 'last_name' => $last_name, 'status' => $status];
        foreach ($original_data as $field => $old_value) {
            $new_value = $new_data[$field] ?? null;
            if ($old_value !== $new_value) {
                $changes['before'][$field] = $old_value;
                $changes['after'][$field] = $new_value;
            }
        }

        logActivity($conn, "Updated email account: {$email_data['email_address']}", $email_data['email_address'], $email_data['domain_name'], [
            'category' => 'email',
            'resource_type' => 'email',
            'resource_id' => $email_id,
            'changes' => $changes
        ]);
        header('Location: emails.php?success=updated');
    } else {
        header('Location: emails.php?error=Failed to update email: ' . $conn->error);
    }
    exit;
}

/**
 * Toggle email status (suspend/activate) via AJAX
 */
function toggleStatus() {
    global $conn;

    $email_id = intval($_POST['email_id'] ?? 0);

    if ($email_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid email ID']);
    }

    // Get email info
    $stmt = $conn->prepare("SELECT email_address, domain_id, status FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Email not found']);
    }

    $email = $result->fetch_assoc();

    // Check access
    if (!canAccessDomain($email['domain_id'])) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
    }

    // Check if domain is removed from Google
    $domain_error = checkDomainAvailable($email['domain_id']);
    if ($domain_error) {
        jsonResponse(['success' => false, 'error' => $domain_error]);
    }

    $new_status = ($email['status'] === 'active') ? 'suspended' : 'active';
    $suspend = ($new_status === 'suspended');

    // Update in Google first
    $google_result = toggleGoogleUserStatus($email['email_address'], $suspend);

    if (!$google_result['success']) {
        jsonResponse(['success' => false, 'error' => 'Google API Error: ' . $google_result['error']]);
    }

    // Update in database
    $update = $conn->prepare("UPDATE email_accounts SET status = ? WHERE id = ?");
    $update->bind_param('si', $new_status, $email_id);

    if ($update->execute()) {
        $action = $suspend ? 'Suspended' : 'Activated';
        logActivity($conn, "$action email: {$email['email_address']}", $email['email_address'], null, [
            'category' => 'email',
            'resource_type' => 'email',
            'resource_id' => $email_id,
            'changes' => [
                'action' => 'status_change',
                'before' => ['status' => $email['status']],
                'after' => ['status' => $new_status]
            ]
        ]);
        jsonResponse([
            'success' => true,
            'new_status' => $new_status,
            'message' => "Email {$action} successfully"
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Database update failed']);
    }
}

/**
 * Delete email account - Initiates OTP verification
 */
function deleteEmail() {
    global $conn;

    $email_id = intval($_POST['email_id'] ?? 0);

    if ($email_id <= 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid email ID']);
        }
        header('Location: emails.php?error=Invalid email');
        exit;
    }

    // Get email info
    $stmt = $conn->prepare("SELECT email_address, domain_id FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Email not found']);
        }
        header('Location: emails.php?error=Email not found');
        exit;
    }

    $email = $result->fetch_assoc();

    // Check access
    if (!canAccessDomain($email['domain_id'])) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Access denied']);
        }
        header('Location: emails.php?error=Access denied');
        exit;
    }

    // Return info for OTP verification
    if (isAjax()) {
        jsonResponse([
            'success' => true,
            'requires_otp' => true,
            'email_id' => $email_id,
            'email_address' => $email['email_address'],
            'action_type' => 'delete_email'
        ]);
    }

    // Non-AJAX fallback - redirect to delete confirmation
    header('Location: emails.php?confirm_delete=' . $email_id);
    exit;
}

/**
 * Delete email after OTP verification
 */
function deleteEmailConfirmed() {
    global $conn;

    $email_id = intval($_POST['email_id'] ?? 0);

    if ($email_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid email ID']);
    }

    // Get email info
    $stmt = $conn->prepare("SELECT email_address, domain_id FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Email not found']);
    }

    $email = $result->fetch_assoc();

    // Check access
    if (!canAccessDomain($email['domain_id'])) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
    }

    // Delete from Google first
    $google_result = deleteGoogleWorkspaceUser($email['email_address']);

    if (!$google_result['success']) {
        jsonResponse(['success' => false, 'error' => 'Google API Error: ' . $google_result['error']]);
    }

    // Delete associated aliases from database
    $conn->query("DELETE FROM email_aliases WHERE email_account_id = $email_id");

    // Delete email from database
    $delete = $conn->prepare("DELETE FROM email_accounts WHERE id = ?");
    $delete->bind_param('i', $email_id);

    if ($delete->execute()) {
        logActivity($conn, "Deleted email account: {$email['email_address']}", $email['email_address'], null, [
            'category' => 'email',
            'resource_type' => 'email',
            'resource_id' => $email_id,
            'changes' => [
                'action' => 'delete',
                'deleted_email' => [
                    'id' => $email_id,
                    'email_address' => $email['email_address']
                ]
            ]
        ]);
        jsonResponse([
            'success' => true,
            'message' => 'Email deleted successfully',
            'warning' => $google_result['warning'] ?? null
        ]);
    } else {
        // Google user deleted but DB delete failed - track for recovery
        require_once 'includes/error_recovery.php';
        recordFailedOperation($conn, [
            'operation_type' => 'delete_email',
            'resource_type' => 'email',
            'resource_identifier' => $email['email_address'],
            'domain_id' => $email['domain_id'],
            'error_message' => $conn->error,
            'error_code' => ERR_DB_DELETE_FAILED
        ]);
        jsonResponse(['success' => false, 'error' => 'Deleted from Google but failed to update locally. The system will auto-recover.']);
    }
}

/**
 * Reset email password
 */
function resetPassword() {
    global $conn;

    $email_id = intval($_POST['email_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    if ($email_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid email ID']);
    }

    if (empty($new_password)) {
        jsonResponse(['success' => false, 'error' => 'Password is required']);
    }

    // Get email info
    $stmt = $conn->prepare("SELECT email_address, domain_id FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Email not found']);
    }

    $email = $result->fetch_assoc();

    // Check access
    if (!canAccessDomain($email['domain_id'])) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
    }

    // Check if domain is removed from Google
    $domain_error = checkDomainAvailable($email['domain_id']);
    if ($domain_error) {
        jsonResponse(['success' => false, 'error' => $domain_error]);
    }

    // Validate password with full requirements
    $password_validation = validatePassword($new_password, $email['email_address']);
    if (!$password_validation['valid']) {
        jsonResponse(['success' => false, 'error' => formatPasswordErrors($password_validation['errors'])]);
    }

    // Update password in Google
    $google_result = updateGoogleUserPassword($email['email_address'], $new_password);

    if (!$google_result['success']) {
        jsonResponse(['success' => false, 'error' => 'Google API Error: ' . $google_result['error']]);
    }

    logActivity($conn, "Reset password for: {$email['email_address']}", $email['email_address'], null, [
        'category' => 'email',
        'resource_type' => 'email',
        'resource_id' => $email_id,
        'changes' => [
            'action' => 'password_reset',
            'email_address' => $email['email_address']
        ]
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
}
