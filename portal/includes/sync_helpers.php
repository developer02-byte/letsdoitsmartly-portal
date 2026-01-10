<?php
/**
 * Sync Session Tracking Helpers
 * Functions for creating and updating sync session records
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Create a new sync session record
 * Returns the sync_session_id for linking import_logs
 */
function createSyncSession($conn, $data) {
    $stmt = $conn->prepare("
        INSERT INTO sync_sessions
        (sync_type, initiated_by, initiated_by_name, status, started_at)
        VALUES (?, ?, ?, 'in_progress', NOW())
    ");

    $stmt->bind_param(
        'sis',
        $data['sync_type'],
        $data['initiated_by'],
        $data['initiated_by_name']
    );

    $stmt->execute();
    $sync_session_id = $conn->insert_id;

    error_log("Created sync session #$sync_session_id - Type: {$data['sync_type']}, Initiated by: {$data['initiated_by_name']}");

    return $sync_session_id;
}

/**
 * Update sync session with results after completion
 */
function updateSyncSession($conn, $sync_session_id, $result) {
    // Determine final status
    $status = 'completed';
    if (!empty($result['had_errors'])) {
        $status = 'partial';  // Completed with some errors
    }
    if (isset($result['status']) && $result['status'] === 'failed') {
        $status = 'failed';
    }

    // Extract statistics from result
    $total_domains = $result['domains']['total'] ?? 0;
    $total_users_fetched = $result['users']['total'] ?? 0;
    $total_users_imported = $result['users']['imported'] ?? 0;
    $total_users_updated = $result['users']['updated'] ?? 0;
    $total_aliases_added = $result['aliases']['added'] ?? 0;
    $total_aliases_removed = $result['aliases']['removed'] ?? 0;
    $had_errors = !empty($result['had_errors']) ? 1 : 0;
    $error_summary = $result['error_summary'] ?? null;

    $stmt = $conn->prepare("
        UPDATE sync_sessions
        SET status = ?,
            completed_at = NOW(),
            duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()),
            total_domains_processed = ?,
            total_users_fetched = ?,
            total_users_imported = ?,
            total_users_updated = ?,
            total_aliases_added = ?,
            total_aliases_removed = ?,
            had_errors = ?,
            error_summary = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        'siiiiiisi',
        $status,
        $total_domains,
        $total_users_fetched,
        $total_users_imported,
        $total_users_updated,
        $total_aliases_added,
        $total_aliases_removed,
        $had_errors,
        $error_summary,
        $sync_session_id
    );

    $stmt->execute();

    error_log("Updated sync session #$sync_session_id - Status: $status, Duration: " . ($result['duration'] ?? 'N/A') . "s");
}

/**
 * Link an import_log to a sync session
 * Call this when creating import_logs records
 */
function linkImportLogToSession($conn, $import_log_id, $sync_session_id) {
    if (empty($import_log_id) || empty($sync_session_id)) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE import_logs
        SET sync_session_id = ?
        WHERE id = ?
    ");

    $stmt->bind_param('ii', $sync_session_id, $import_log_id);
    return $stmt->execute();
}

/**
 * Get sync session details
 */
function getSyncSession($conn, $sync_session_id) {
    $stmt = $conn->prepare("
        SELECT ss.*,
               u.name as initiated_by_user_name,
               u.email as initiated_by_email,
               COUNT(il.id) as domain_count
        FROM sync_sessions ss
        LEFT JOIN users u ON ss.initiated_by = u.id
        LEFT JOIN import_logs il ON il.sync_session_id = ss.id
        WHERE ss.id = ?
        GROUP BY ss.id
    ");

    $stmt->bind_param('i', $sync_session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc();
}

/**
 * Get import logs for a specific sync session
 */
function getImportLogsBySession($conn, $sync_session_id) {
    $stmt = $conn->prepare("
        SELECT il.*, d.domain_name
        FROM import_logs il
        JOIN domains d ON il.domain_id = d.id
        WHERE il.sync_session_id = ?
        ORDER BY il.started_at ASC
    ");

    $stmt->bind_param('i', $sync_session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    return $logs;
}
