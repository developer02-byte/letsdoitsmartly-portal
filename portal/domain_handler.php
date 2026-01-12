<?php
/**
 * Domain Handler
 * Handles domain CRUD operations with Google Workspace verification
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/google_functions.php';

requireLogin();

// Only super admin can manage domains
if (!isSuperAdmin()) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
    }
    header('Location: domains.php?error=Access denied');
    exit;
}

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid security token']);
        }
        header('Location: domains.php?error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addDomain();
        break;
    case 'edit':
        editDomain();
        break;
    case 'delete':
        deleteDomain();
        break;
    case 'delete_confirmed':
        deleteDomainConfirmed();
        break;
    case 'sync':
        syncDomain();
        break;
    case 'verify_google':
        verifyGoogleDomain();
        break;
    default:
        header('Location: domains.php');
        exit;
}

/**
 * Add new domain - verifies it exists in Google Workspace
 */
function addDomain() {
    global $conn;

    $domain_name = strtolower(trim($_POST['domain_name'] ?? ''));
    $domain_owner_id_raw = $_POST['domain_owner_id'] ?? '';
    $domain_owner_id = $domain_owner_id_raw === '' ? null : intval($domain_owner_id_raw);
    $status = $_POST['status'] ?? 'active';
    $verify_google = isset($_POST['verify_google']) ? true : false;

    // Validate domain name
    if (empty($domain_name)) {
        header('Location: domains.php?error=Domain name is required');
        exit;
    }

    // Basic domain format validation
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/', $domain_name)) {
        header('Location: domains.php?error=Invalid domain format');
        exit;
    }

    // Remove www. if present
    $domain_name = preg_replace('/^www\./', '', $domain_name);

    // Check if domain already exists in database
    $check = $conn->prepare("SELECT id FROM domains WHERE domain_name = ?");
    $check->bind_param('s', $domain_name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: domains.php?error=Domain already exists in database');
        exit;
    }

    // Verify domain exists in Google Workspace if requested
    if ($verify_google) {
        $google_result = checkGoogleDomain($domain_name);
        if (!$google_result['success']) {
            header('Location: domains.php?error=Domain not found in Google Workspace: ' . urlencode($google_result['error']));
            exit;
        }
        if (!$google_result['verified']) {
            header('Location: domains.php?error=Domain exists in Google but is not verified');
            exit;
        }
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // Insert domain (owner can be NULL for unassigned domains)
    if ($domain_owner_id === null) {
        $stmt = $conn->prepare("INSERT INTO domains (domain_name, domain_owner_id, status) VALUES (?, NULL, ?)");
        $stmt->bind_param('ss', $domain_name, $status);
    } else {
        $stmt = $conn->prepare("INSERT INTO domains (domain_name, domain_owner_id, status) VALUES (?, ?, ?)");
        $stmt->bind_param('sis', $domain_name, $domain_owner_id, $status);
    }

    if ($stmt->execute()) {
        $new_domain_id = $conn->insert_id;

        // Get owner name for logging
        $owner_name = null;
        if ($domain_owner_id !== null) {
            $owner_stmt = $conn->prepare("SELECT do.id, u.name FROM domain_owners do JOIN users u ON do.user_id = u.id WHERE do.id = ?");
            $owner_stmt->bind_param('i', $domain_owner_id);
            $owner_stmt->execute();
            $owner_result = $owner_stmt->get_result();
            if ($owner_result->num_rows > 0) {
                $owner_name = $owner_result->fetch_assoc()['name'];
            }
        }

        $log_msg = $domain_owner_id === null ? "Added unassigned domain: $domain_name" : "Added domain: $domain_name (assigned to $owner_name)";
        logActivity($conn, $log_msg, null, $domain_name, [
            'category' => 'domain',
            'resource_type' => 'domain',
            'resource_id' => $new_domain_id,
            'changes' => [
                'action' => 'create',
                'domain' => [
                    'id' => $new_domain_id,
                    'domain_name' => $domain_name,
                    'status' => $status,
                    'owner_id' => $domain_owner_id,
                    'owner_name' => $owner_name
                ]
            ]
        ]);
        header('Location: domains.php?success=added');
    } else {
        header('Location: domains.php?error=Failed to add domain: ' . $conn->error);
    }
    exit;
}

/**
 * Edit existing domain
 */
function editDomain() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);
    $domain_owner_id_raw = $_POST['domain_owner_id'] ?? '';
    $domain_owner_id = $domain_owner_id_raw === '' ? null : intval($domain_owner_id_raw);
    $status = $_POST['status'] ?? 'active';

    if ($domain_id <= 0) {
        header('Location: domains.php?error=Invalid domain');
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // Get current domain for logging (with owner info for before/after comparison)
    $current = $conn->prepare("SELECT d.*, do.id as owner_id, u.name as owner_name
                               FROM domains d
                               LEFT JOIN domain_owners do ON d.domain_owner_id = do.id
                               LEFT JOIN users u ON do.user_id = u.id
                               WHERE d.id = ?");
    $current->bind_param('i', $domain_id);
    $current->execute();
    $result = $current->get_result();
    if ($result->num_rows === 0) {
        header('Location: domains.php?error=Domain not found');
        exit;
    }
    $current_domain = $result->fetch_assoc();
    $domain_name = $current_domain['domain_name'];
    $original_data = [
        'status' => $current_domain['status'],
        'owner_id' => $current_domain['domain_owner_id'],
        'owner_name' => $current_domain['owner_name']
    ];

    // Update domain (owner can be NULL for unassigned domains)
    if ($domain_owner_id === null) {
        $stmt = $conn->prepare("UPDATE domains SET domain_owner_id = NULL, status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $domain_id);
    } else {
        $stmt = $conn->prepare("UPDATE domains SET domain_owner_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param('isi', $domain_owner_id, $status, $domain_id);
    }

    if ($stmt->execute()) {
        // Get new owner name for logging
        $new_owner_name = null;
        if ($domain_owner_id !== null) {
            $owner_stmt = $conn->prepare("SELECT u.name FROM domain_owners do JOIN users u ON do.user_id = u.id WHERE do.id = ?");
            $owner_stmt->bind_param('i', $domain_owner_id);
            $owner_stmt->execute();
            $owner_result = $owner_stmt->get_result();
            if ($owner_result->num_rows > 0) {
                $new_owner_name = $owner_result->fetch_assoc()['name'];
            }
        }

        // Build changes array
        $changes = ['action' => 'update', 'before' => [], 'after' => []];
        if ($original_data['status'] !== $status) {
            $changes['before']['status'] = $original_data['status'];
            $changes['after']['status'] = $status;
        }
        if ($original_data['owner_id'] != $domain_owner_id) {
            $changes['before']['owner'] = $original_data['owner_name'] ?? 'Unassigned';
            $changes['after']['owner'] = $new_owner_name ?? 'Unassigned';
        }

        // Create descriptive log message
        $log_msg = "Updated domain: $domain_name";
        if (isset($changes['after']['owner'])) {
            $log_msg = "Assigned domain $domain_name to " . ($new_owner_name ?? 'Unassigned');
        }

        logActivity($conn, $log_msg, null, $domain_name, [
            'category' => 'domain',
            'resource_type' => 'domain',
            'resource_id' => $domain_id,
            'changes' => $changes
        ]);
        header('Location: domains.php?success=updated');
    } else {
        header('Location: domains.php?error=Failed to update domain: ' . $conn->error);
    }
    exit;
}

/**
 * Delete domain - initiates OTP verification
 */
function deleteDomain() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);

    if ($domain_id <= 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Invalid domain ID']);
        }
        header('Location: domains.php?error=Invalid domain');
        exit;
    }

    // Get domain info
    $stmt = $conn->prepare("
        SELECT d.domain_name, COUNT(e.id) as email_count
        FROM domains d
        LEFT JOIN email_accounts e ON d.id = e.domain_id
        WHERE d.id = ?
        GROUP BY d.id
    ");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Domain not found']);
        }
        header('Location: domains.php?error=Domain not found');
        exit;
    }

    $domain = $result->fetch_assoc();

    // For AJAX, return info for OTP verification
    if (isAjax()) {
        jsonResponse([
            'success' => true,
            'requires_otp' => true,
            'domain_id' => $domain_id,
            'domain_name' => $domain['domain_name'],
            'email_count' => $domain['email_count'],
            'action_type' => 'delete_domain',
            'warning' => $domain['email_count'] > 0 ?
                "This will also delete {$domain['email_count']} email account(s) and all aliases!" : null
        ]);
    }

    // Non-AJAX fallback
    header('Location: domains.php?confirm_delete=' . $domain_id);
    exit;
}

/**
 * Delete domain after OTP verification
 */
function deleteDomainConfirmed() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);

    if ($domain_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid domain ID']);
    }

    // Get domain info
    $stmt = $conn->prepare("SELECT domain_name FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Domain not found']);
    }

    $domain_name = $result->fetch_assoc()['domain_name'];

    // Get all email accounts for this domain to delete from Google
    $emails = $conn->query("SELECT email_address FROM email_accounts WHERE domain_id = $domain_id");
    $google_errors = [];

    while ($email = $emails->fetch_assoc()) {
        $google_result = deleteGoogleWorkspaceUser($email['email_address']);
        if (!$google_result['success'] && !isset($google_result['warning'])) {
            $google_errors[] = $email['email_address'] . ': ' . $google_result['error'];
        }
    }

    // Delete from database even if some Google deletions failed
    // (user may have been deleted manually from Google)

    // Delete associated aliases first (via email_accounts)
    $conn->query("DELETE ea FROM email_aliases ea
                  JOIN email_accounts e ON ea.email_account_id = e.id
                  WHERE e.domain_id = $domain_id");

    // Delete associated email accounts
    $conn->query("DELETE FROM email_accounts WHERE domain_id = $domain_id");

    // Delete domain
    $delete = $conn->prepare("DELETE FROM domains WHERE id = ?");
    $delete->bind_param('i', $domain_id);

    if ($delete->execute()) {
        logActivity($conn, "Deleted domain: $domain_name", null, $domain_name, [
            'category' => 'domain',
            'resource_type' => 'domain',
            'resource_id' => $domain_id,
            'changes' => [
                'action' => 'delete',
                'deleted_domain' => [
                    'id' => $domain_id,
                    'domain_name' => $domain_name
                ],
                'google_errors' => !empty($google_errors) ? $google_errors : null
            ]
        ]);

        $response = [
            'success' => true,
            'message' => 'Domain and all associated data deleted successfully'
        ];

        if (!empty($google_errors)) {
            $response['warnings'] = $google_errors;
        }

        jsonResponse($response);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to delete domain from database']);
    }
}

/**
 * Sync domain with Google Workspace
 */
function syncDomain() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);

    if ($domain_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid domain ID']);
    }

    // Get domain info
    $stmt = $conn->prepare("SELECT domain_name FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Domain not found']);
    }

    $domain_name = $result->fetch_assoc()['domain_name'];

    // Perform sync
    $sync_result = syncGoogleUsersToDatabase($domain_id, $domain_name, $conn);

    if ($sync_result['success']) {
        logActivity($conn, "Synced domain: $domain_name", null, $domain_name, [
            'category' => 'sync',
            'resource_type' => 'domain',
            'resource_id' => $domain_id,
            'changes' => [
                'action' => 'sync',
                'domain_name' => $domain_name,
                'stats' => $sync_result['stats'] ?? null
            ]
        ]);
        jsonResponse([
            'success' => true,
            'message' => "Domain synced successfully",
            'stats' => $sync_result['stats']
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => $sync_result['error']]);
    }
}

/**
 * Verify if domain exists in Google Workspace (AJAX)
 */
function verifyGoogleDomain() {
    $domain_name = strtolower(trim($_POST['domain_name'] ?? ''));

    if (empty($domain_name)) {
        jsonResponse(['success' => false, 'error' => 'Domain name is required']);
    }

    $result = checkGoogleDomain($domain_name);

    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'verified' => $result['verified'],
            'primary' => $result['primary'],
            'message' => $result['verified'] ?
                'Domain found and verified in Google Workspace' :
                'Domain found but not verified in Google Workspace'
        ]);
    } else {
        jsonResponse($result);
    }
}
