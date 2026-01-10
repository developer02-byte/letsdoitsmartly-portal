<?php
/**
 * Resource Locking
 * Prevents race conditions on concurrent operations
 */

if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

define('RESOURCE_LOCK_TIMEOUT_SECONDS', 30);

/**
 * Clean up expired locks
 */
function cleanupExpiredLocks($conn) {
    $conn->query("DELETE FROM resource_locks WHERE expires_at < NOW()");
}

/**
 * Acquire a lock on a resource
 */
function acquireResourceLock($conn, $type, $identifier, $reason = null) {
    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    $expires_at = date('Y-m-d H:i:s', time() + RESOURCE_LOCK_TIMEOUT_SECONDS);

    // Clean up expired locks first
    cleanupExpiredLocks($conn);

    // Try to insert a new lock
    $stmt = $conn->prepare("
        INSERT INTO resource_locks (resource_type, resource_identifier, locked_by, lock_reason, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            locked_at = IF(expires_at < NOW(), NOW(), locked_at),
            locked_by = IF(expires_at < NOW(), VALUES(locked_by), locked_by),
            lock_reason = IF(expires_at < NOW(), VALUES(lock_reason), lock_reason),
            expires_at = IF(expires_at < NOW(), VALUES(expires_at), expires_at)
    ");
    $stmt->bind_param('ssiss', $type, $identifier, $user_id, $reason, $expires_at);
    $stmt->execute();

    // Verify we got the lock by checking if we're the owner
    $check = $conn->prepare("
        SELECT locked_by FROM resource_locks
        WHERE resource_type = ? AND resource_identifier = ? AND expires_at > NOW()
    ");
    $check->bind_param('ss', $type, $identifier);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();

    if ($result && ($result['locked_by'] == $user_id || $result['locked_by'] === null)) {
        return ['success' => true];
    }

    return [
        'success' => false,
        'error' => 'Resource is currently being modified by another user',
        'error_code' => 'RESOURCE_LOCKED'
    ];
}

/**
 * Release a resource lock
 */
function releaseResourceLock($conn, $type, $identifier) {
    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

    // Release lock if we own it or if it's expired
    if ($user_id !== null) {
        $stmt = $conn->prepare("
            DELETE FROM resource_locks
            WHERE resource_type = ? AND resource_identifier = ?
            AND (locked_by = ? OR expires_at < NOW())
        ");
        $stmt->bind_param('ssi', $type, $identifier, $user_id);
    } else {
        $stmt = $conn->prepare("
            DELETE FROM resource_locks
            WHERE resource_type = ? AND resource_identifier = ?
        ");
        $stmt->bind_param('ss', $type, $identifier);
    }

    return $stmt->execute();
}

/**
 * Check if resource is locked
 */
function isResourceLocked($conn, $type, $identifier) {
    // Clean expired first
    cleanupExpiredLocks($conn);

    $stmt = $conn->prepare("
        SELECT locked_by, lock_reason, expires_at, locked_at
        FROM resource_locks
        WHERE resource_type = ? AND resource_identifier = ? AND expires_at > NOW()
    ");
    $stmt->bind_param('ss', $type, $identifier);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return ['locked' => false];
    }

    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

    return [
        'locked' => true,
        'by_current_user' => ($result['locked_by'] == $user_id),
        'locked_by' => $result['locked_by'],
        'reason' => $result['lock_reason'],
        'expires_at' => $result['expires_at'],
        'locked_at' => $result['locked_at']
    ];
}

/**
 * Extend lock expiry (call periodically for long operations)
 */
function extendResourceLock($conn, $type, $identifier, $extra_seconds = null) {
    $user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
    $extra = $extra_seconds ?? RESOURCE_LOCK_TIMEOUT_SECONDS;
    $new_expires = date('Y-m-d H:i:s', time() + $extra);

    $stmt = $conn->prepare("
        UPDATE resource_locks
        SET expires_at = ?
        WHERE resource_type = ? AND resource_identifier = ?
        AND (locked_by = ? OR locked_by IS NULL)
    ");
    $stmt->bind_param('sssi', $new_expires, $type, $identifier, $user_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Execute operation with lock protection
 * Usage:
 * $result = withResourceLock($conn, 'email', 'user@domain.com', 'creating', function() {
 *     // Your code here
 *     return ['success' => true];
 * });
 */
function withResourceLock($conn, $type, $identifier, $reason, $callback) {
    $lock = acquireResourceLock($conn, $type, $identifier, $reason);

    if (!$lock['success']) {
        require_once __DIR__ . '/error_messages.php';
        return [
            'success' => false,
            'error' => $lock['error'],
            'error_code' => ErrorCodes::RESOURCE_LOCKED
        ];
    }

    try {
        $result = $callback();
        return $result;
    } finally {
        releaseResourceLock($conn, $type, $identifier);
    }
}

/**
 * Force release all locks (admin function)
 */
function forceReleaseAllLocks($conn) {
    return $conn->query("DELETE FROM resource_locks");
}

/**
 * Get all active locks (for admin dashboard)
 */
function getActiveLocks($conn) {
    cleanupExpiredLocks($conn);

    $result = $conn->query("
        SELECT rl.*, u.name as locked_by_name
        FROM resource_locks rl
        LEFT JOIN users u ON rl.locked_by = u.id
        WHERE rl.expires_at > NOW()
        ORDER BY rl.locked_at DESC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
