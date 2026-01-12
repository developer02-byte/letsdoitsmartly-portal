<?php
/**
 * Error Recovery Functions
 * Tracks and resolves Google/DB inconsistencies
 */

if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

// Error code definitions
define('ERR_DB_INSERT_FAILED', 'DB_INSERT_FAILED');
define('ERR_DB_UPDATE_FAILED', 'DB_UPDATE_FAILED');
define('ERR_DB_DELETE_FAILED', 'DB_DELETE_FAILED');
define('ERR_GOOGLE_SYNC_MISMATCH', 'GOOGLE_SYNC_MISMATCH');
define('ERR_ORPHAN_DETECTED', 'ORPHAN_DETECTED');
define('ERR_STALE_ALIAS', 'STALE_ALIAS');

/**
 * Record a failed operation for later recovery
 */
function recordFailedOperation($conn, $params) {
    $stmt = $conn->prepare("
        INSERT INTO failed_operations
        (operation_type, resource_type, resource_identifier, google_user_id,
         related_email, domain_id, operation_data, error_message, error_code, initiated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $operation_data = isset($params['operation_data']) ? json_encode($params['operation_data']) : null;
    $initiated_by = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    $google_user_id = $params['google_user_id'] ?? null;
    $related_email = $params['related_email'] ?? null;
    $domain_id = $params['domain_id'] ?? null;

    $stmt->bind_param('sssssisssi',
        $params['operation_type'],
        $params['resource_type'],
        $params['resource_identifier'],
        $google_user_id,
        $related_email,
        $domain_id,
        $operation_data,
        $params['error_message'],
        $params['error_code'],
        $initiated_by
    );

    $result = $stmt->execute();

    // Also log to error_log for immediate visibility
    error_log(sprintf(
        "FAILED_OPERATION: type=%s resource=%s error=%s code=%s",
        $params['operation_type'],
        $params['resource_identifier'],
        $params['error_message'],
        $params['error_code']
    ));

    return $result ? $conn->insert_id : false;
}

/**
 * Get pending failed operations count
 */
function getPendingFailedOperationsCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM failed_operations WHERE status = 'pending'");
    if (!$result) return 0;
    return $result->fetch_assoc()['count'];
}

/**
 * Get failed operations for dashboard/admin view
 */
function getFailedOperations($conn, $filters = []) {
    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($filters['status'])) {
        $where[] = "fo.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }

    if (!empty($filters['operation_type'])) {
        $where[] = "fo.operation_type = ?";
        $params[] = $filters['operation_type'];
        $types .= "s";
    }

    $limit = $filters['limit'] ?? 50;
    $offset = $filters['offset'] ?? 0;

    $sql = "SELECT fo.*, u.name as initiated_by_name, r.name as resolved_by_name
            FROM failed_operations fo
            LEFT JOIN users u ON fo.initiated_by = u.id
            LEFT JOIN users r ON fo.resolved_by = r.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY fo.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($types) && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Attempt to auto-resolve a failed operation
 */
function attemptAutoResolve($conn, $failed_op_id) {
    $stmt = $conn->prepare("SELECT * FROM failed_operations WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $failed_op_id);
    $stmt->execute();
    $op = $stmt->get_result()->fetch_assoc();

    if (!$op) {
        return ['success' => false, 'error' => 'Operation not found or not pending'];
    }

    // Update attempt counter
    $conn->query("UPDATE failed_operations SET resolution_attempts = resolution_attempts + 1,
                  last_attempt_at = NOW() WHERE id = $failed_op_id");

    $resolved = false;
    $notes = '';

    switch ($op['operation_type']) {
        case 'create_email':
            $resolved = resolveCreateEmail($conn, $op, $notes);
            break;
        case 'delete_email':
            $resolved = resolveDeleteEmail($conn, $op, $notes);
            break;
        case 'create_alias':
            $resolved = resolveCreateAlias($conn, $op, $notes);
            break;
        case 'delete_alias':
            $resolved = resolveDeleteAlias($conn, $op, $notes);
            break;
        default:
            $notes = 'Unknown operation type';
            break;
    }

    if ($resolved) {
        $stmt = $conn->prepare("UPDATE failed_operations SET status = 'auto_resolved',
                                resolved_at = NOW(), resolution_notes = ? WHERE id = ?");
        $stmt->bind_param('si', $notes, $failed_op_id);
        $stmt->execute();
        return ['success' => true, 'message' => 'Auto-resolved: ' . $notes];
    }

    return ['success' => false, 'error' => 'Could not auto-resolve: ' . $notes];
}

/**
 * Resolve create_email: Insert the record that failed
 */
function resolveCreateEmail($conn, $op, &$notes) {
    $data = json_decode($op['operation_data'], true) ?? [];

    // Check if already exists (maybe manually fixed or synced)
    $check = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $check->bind_param('s', $op['resource_identifier']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $notes = 'Email already exists in database (resolved by sync or manual fix)';
        return true;
    }

    // Try to insert
    $stmt = $conn->prepare("INSERT INTO email_accounts
                            (domain_id, email_address, first_name, last_name, status, google_user_id)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $first_name = $data['first_name'] ?? '';
    $last_name = $data['last_name'] ?? '';
    $status = $data['status'] ?? 'active';

    $stmt->bind_param('isssss',
        $op['domain_id'],
        $op['resource_identifier'],
        $first_name,
        $last_name,
        $status,
        $op['google_user_id']
    );

    if ($stmt->execute()) {
        $notes = 'Successfully inserted email into database';
        return true;
    }

    $notes = 'DB insert still failing: ' . $conn->error;
    return false;
}

/**
 * Resolve delete_email: Remove orphan record from DB
 */
function resolveDeleteEmail($conn, $op, &$notes) {
    // Check if email still exists in database
    $check = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $check->bind_param('s', $op['resource_identifier']);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $notes = 'Email already deleted from database';
        return true;
    }

    // Try to verify user doesn't exist in Google before deleting
    // For safety, we'll just try the delete - if Google deletion succeeded, this is safe

    // First delete associated aliases
    $email_check = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $email_check->bind_param('s', $op['resource_identifier']);
    $email_check->execute();
    $email = $email_check->get_result()->fetch_assoc();

    if ($email) {
        $conn->query("DELETE FROM email_aliases WHERE email_account_id = " . intval($email['id']));
    }

    // Delete the email account
    $delete = $conn->prepare("DELETE FROM email_accounts WHERE email_address = ?");
    $delete->bind_param('s', $op['resource_identifier']);
    if ($delete->execute()) {
        $notes = 'Deleted orphan email record from database';
        return true;
    }

    $notes = 'Failed to delete from DB: ' . $conn->error;
    return false;
}

/**
 * Resolve create_alias: Insert the alias record
 */
function resolveCreateAlias($conn, $op, &$notes) {
    // Check if already exists
    $check = $conn->prepare("SELECT id FROM email_aliases WHERE alias_address = ?");
    $check->bind_param('s', $op['resource_identifier']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $notes = 'Alias already exists in database';
        return true;
    }

    // Get email account ID
    $email_stmt = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
    $email_stmt->bind_param('s', $op['related_email']);
    $email_stmt->execute();
    $email = $email_stmt->get_result()->fetch_assoc();

    if (!$email) {
        $notes = 'Parent email account not found in database';
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO email_aliases (email_account_id, alias_address) VALUES (?, ?)");
    $stmt->bind_param('is', $email['id'], $op['resource_identifier']);

    if ($stmt->execute()) {
        $notes = 'Successfully inserted alias into database';
        return true;
    }

    $notes = 'DB insert still failing: ' . $conn->error;
    return false;
}

/**
 * Resolve delete_alias: Remove stale alias from DB
 */
function resolveDeleteAlias($conn, $op, &$notes) {
    // Check if alias still exists in database
    $check = $conn->prepare("SELECT id FROM email_aliases WHERE alias_address = ?");
    $check->bind_param('s', $op['resource_identifier']);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $notes = 'Alias already deleted from database';
        return true;
    }

    // Delete the alias
    $delete = $conn->prepare("DELETE FROM email_aliases WHERE alias_address = ?");
    $delete->bind_param('s', $op['resource_identifier']);
    if ($delete->execute()) {
        $notes = 'Deleted stale alias from database';
        return true;
    }

    $notes = 'Failed to delete alias from DB: ' . $conn->error;
    return false;
}

/**
 * Manually resolve a failed operation
 */
function manuallyResolveOperation($conn, $failed_op_id, $notes) {
    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    $stmt = $conn->prepare("UPDATE failed_operations
                            SET status = 'manual_resolved', resolved_at = NOW(),
                                resolved_by = ?, resolution_notes = ?
                            WHERE id = ?");
    $stmt->bind_param('isi', $user_id, $notes, $failed_op_id);
    return $stmt->execute();
}

/**
 * Mark operation as permanently failed
 */
function markPermanentlyFailed($conn, $failed_op_id, $notes) {
    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    $stmt = $conn->prepare("UPDATE failed_operations
                            SET status = 'failed_permanent', resolved_at = NOW(),
                                resolved_by = ?, resolution_notes = ?
                            WHERE id = ?");
    $stmt->bind_param('isi', $user_id, $notes, $failed_op_id);
    return $stmt->execute();
}

/**
 * Process all pending failed operations (called by cron)
 */
function processFailedOperations($conn, $limit = 10) {
    $pending = getFailedOperations($conn, ['status' => 'pending', 'limit' => $limit]);

    $results = [
        'processed' => 0,
        'resolved' => 0,
        'failed' => 0
    ];

    foreach ($pending as $op) {
        $results['processed']++;
        $result = attemptAutoResolve($conn, $op['id']);
        if ($result['success']) {
            $results['resolved']++;
        } else {
            $results['failed']++;
        }
    }

    return $results;
}
