<?php
/**
 * Alias Handler
 * Handles email alias CRUD operations with Google Workspace sync
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/google_functions.php';

requireLogin();

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid security token']);
        }
        header('Location: aliases.php?error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addAlias();
        break;
    case 'delete':
        deleteAlias();
        break;
    case 'delete_confirmed':
        deleteAliasConfirmed();
        break;
    default:
        header('Location: aliases.php');
        exit;
}

/**
 * Check if user can access an email account's domain
 */
function canAccessEmailAccount($email_account_id) {
    global $conn;

    if (isSuperAdmin()) {
        return true;
    }

    $stmt = $conn->prepare("SELECT domain_id FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return false;
    }

    $domain_id = $result->fetch_assoc()['domain_id'];
    $allowed = getAllowedDomainIds();
    return in_array($domain_id, $allowed ?: []);
}

/**
 * Check if the domain for an email account is available (not removed from Google)
 * Returns error message if domain is removed, null if OK
 */
function checkEmailAccountDomainAvailable($email_account_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT domain_id FROM email_accounts WHERE id = ?");
    $stmt->bind_param('i', $email_account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return "Email account not found";
    }

    $domain_id = $result->fetch_assoc()['domain_id'];
    $check = isDomainRemovedFromGoogle($conn, $domain_id);

    if ($check['removed']) {
        $removed_date = $check['removed_at'] ? date('M j, Y', strtotime($check['removed_at'])) : 'unknown date';
        return "Domain was removed from Google Workspace on $removed_date. No operations are allowed.";
    }
    return null;
}

/**
 * Add new alias - Creates in Google first, then database
 */
function addAlias() {
    global $conn;

    $email_account_id = intval($_POST['email_account_id'] ?? 0);
    $alias_username = strtolower(trim($_POST['alias_username'] ?? ''));

    // Validate email account
    if ($email_account_id <= 0) {
        header('Location: aliases.php?error=Please select an email account');
        exit;
    }

    // Check access
    if (!canAccessEmailAccount($email_account_id)) {
        header('Location: aliases.php?error=Access denied');
        exit;
    }

    // Check if domain is removed from Google
    $domain_error = checkEmailAccountDomainAvailable($email_account_id);
    if ($domain_error) {
        header('Location: aliases.php?error=' . urlencode($domain_error));
        exit;
    }

    // Validate alias username
    if (empty($alias_username)) {
        header('Location: aliases.php?error=Alias username is required');
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $alias_username)) {
        header('Location: aliases.php?error=Invalid alias format. Use only letters, numbers, dots, hyphens, and underscores.');
        exit;
    }

    // Get email account and domain info
    $stmt = $conn->prepare("
        SELECT e.email_address, d.domain_name
        FROM email_accounts e
        JOIN domains d ON e.domain_id = d.id
        WHERE e.id = ?
    ");
    $stmt->bind_param('i', $email_account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Location: aliases.php?error=Email account not found');
        exit;
    }

    $email_data = $result->fetch_assoc();
    $user_email = $email_data['email_address'];
    $alias_address = $alias_username . '@' . $email_data['domain_name'];

    // Check if alias already exists in database
    $check = $conn->prepare("SELECT id FROM email_aliases WHERE alias_address = ?");
    $check->bind_param('s', $alias_address);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: aliases.php?error=This alias already exists');
        exit;
    }

    // Check if alias conflicts with existing email
    $check_email = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $check_email->bind_param('s', $alias_address);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        header('Location: aliases.php?error=This address is already used as a primary email');
        exit;
    }

    // Add alias in Google Workspace FIRST
    $google_result = addGoogleUserAlias($user_email, $alias_address);

    if (!$google_result['success']) {
        header('Location: aliases.php?error=Google API Error: ' . urlencode($google_result['error']));
        exit;
    }

    // Insert alias into database
    $insert = $conn->prepare("INSERT INTO email_aliases (email_account_id, alias_address) VALUES (?, ?)");
    $insert->bind_param('is', $email_account_id, $alias_address);

    if ($insert->execute()) {
        $alias_id = $conn->insert_id;
        logActivity($conn, "Added alias: $alias_address -> $user_email", $alias_address, null, [
            'category' => 'alias',
            'resource_type' => 'alias',
            'resource_id' => $alias_id,
            'changes' => [
                'action' => 'create',
                'alias' => [
                    'id' => $alias_id,
                    'alias_address' => $alias_address,
                    'target_email' => $user_email,
                    'email_account_id' => $email_account_id
                ]
            ]
        ]);
        header('Location: aliases.php?success=added');
    } else {
        // Google alias was created but DB insert failed - track for recovery
        require_once 'includes/error_recovery.php';
        recordFailedOperation($conn, [
            'operation_type' => 'create_alias',
            'resource_type' => 'alias',
            'resource_identifier' => $alias_address,
            'related_email' => $user_email,
            'domain_id' => null, // Alias spans email account, not domain directly
            'operation_data' => [
                'email_account_id' => $email_account_id
            ],
            'error_message' => $conn->error,
            'error_code' => ERR_DB_INSERT_FAILED
        ]);
        header('Location: aliases.php?error=Created in Google but failed to save locally. The system will auto-recover.');
    }
    exit;
}

/**
 * Delete alias - Initiates OTP verification for AJAX, direct delete for form
 */
function deleteAlias() {
    global $conn;

    $alias_id = intval($_POST['alias_id'] ?? 0);

    if ($alias_id <= 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid alias ID']);
        }
        header('Location: aliases.php?error=Invalid alias');
        exit;
    }

    // Get alias info and check access
    $stmt = $conn->prepare("
        SELECT ea.alias_address, ea.email_account_id, e.email_address, e.domain_id
        FROM email_aliases ea
        JOIN email_accounts e ON ea.email_account_id = e.id
        WHERE ea.id = ?
    ");
    $stmt->bind_param('i', $alias_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Alias not found']);
        }
        header('Location: aliases.php?error=Alias not found');
        exit;
    }

    $alias_data = $result->fetch_assoc();

    // Check access
    if (!canAccessEmailAccount($alias_data['email_account_id'])) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Access denied']);
        }
        header('Location: aliases.php?error=Access denied');
        exit;
    }

    // Check if domain is removed from Google
    $domain_error = checkEmailAccountDomainAvailable($alias_data['email_account_id']);
    if ($domain_error) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => $domain_error]);
        }
        header('Location: aliases.php?error=' . urlencode($domain_error));
        exit;
    }

    // For AJAX requests, return info for OTP verification
    if (isAjax()) {
        jsonResponse([
            'success' => true,
            'requires_otp' => true,
            'alias_id' => $alias_id,
            'alias_address' => $alias_data['alias_address'],
            'action_type' => 'delete_alias'
        ]);
    }

    // Non-AJAX: Direct delete (for simpler operations or if OTP was already verified)
    deleteAliasFromGoogleAndDB($alias_id, $alias_data);
}

/**
 * Delete alias after OTP verification
 */
function deleteAliasConfirmed() {
    global $conn;

    $alias_id = intval($_POST['alias_id'] ?? 0);

    if ($alias_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid alias ID']);
    }

    // Get alias info
    $stmt = $conn->prepare("
        SELECT ea.alias_address, ea.email_account_id, e.email_address, e.domain_id
        FROM email_aliases ea
        JOIN email_accounts e ON ea.email_account_id = e.id
        WHERE ea.id = ?
    ");
    $stmt->bind_param('i', $alias_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Alias not found']);
    }

    $alias_data = $result->fetch_assoc();

    // Check access
    if (!canAccessEmailAccount($alias_data['email_account_id'])) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
    }

    // Check if domain is removed from Google
    $domain_error = checkEmailAccountDomainAvailable($alias_data['email_account_id']);
    if ($domain_error) {
        jsonResponse(['success' => false, 'error' => $domain_error]);
    }

    // Delete from Google and database
    deleteAliasFromGoogleAndDB($alias_id, $alias_data, true);
}

/**
 * Helper function to delete alias from Google and database
 */
function deleteAliasFromGoogleAndDB($alias_id, $alias_data, $ajax = false) {
    global $conn;

    // Delete from Google first
    $google_result = removeGoogleUserAlias($alias_data['email_address'], $alias_data['alias_address']);

    if (!$google_result['success']) {
        if ($ajax) {
            jsonResponse(['success' => false, 'error' => 'Google API Error: ' . $google_result['error']]);
        }
        header('Location: aliases.php?error=Google API Error: ' . urlencode($google_result['error']));
        exit;
    }

    // Delete from database
    $delete = $conn->prepare("DELETE FROM email_aliases WHERE id = ?");
    $delete->bind_param('i', $alias_id);

    if ($delete->execute()) {
        logActivity($conn, "Deleted alias: {$alias_data['alias_address']}", $alias_data['alias_address'], null, [
            'category' => 'alias',
            'resource_type' => 'alias',
            'resource_id' => $alias_id,
            'changes' => [
                'action' => 'delete',
                'deleted_alias' => [
                    'id' => $alias_id,
                    'alias_address' => $alias_data['alias_address'],
                    'target_email' => $alias_data['email_address']
                ]
            ]
        ]);

        if ($ajax) {
            jsonResponse([
                'success' => true,
                'message' => 'Alias deleted successfully',
                'warning' => $google_result['warning'] ?? null
            ]);
        }
        header('Location: aliases.php?success=deleted');
    } else {
        // Google alias deleted but DB delete failed - track for recovery
        require_once 'includes/error_recovery.php';
        recordFailedOperation($conn, [
            'operation_type' => 'delete_alias',
            'resource_type' => 'alias',
            'resource_identifier' => $alias_data['alias_address'],
            'related_email' => $alias_data['email_address'],
            'domain_id' => $alias_data['domain_id'],
            'error_message' => $conn->error,
            'error_code' => ERR_DB_DELETE_FAILED
        ]);

        if ($ajax) {
            jsonResponse(['success' => false, 'error' => 'Deleted from Google but failed to update locally. The system will auto-recover.']);
        }
        header('Location: aliases.php?error=Deleted from Google but failed to update locally. The system will auto-recover.');
    }
    exit;
}
