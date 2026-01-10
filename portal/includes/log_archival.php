<?php
/**
 * Log Archival Functions
 * Hot/Cold Storage Management for 3-Year Retention
 *
 * Hot Storage: Last 90 days in primary tables (fast queries)
 * Cold Storage: 90 days - 3 years in archive tables
 * Deletion: After 3 years
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Archive old logs to cold storage (90+ days old)
 * Moves data from primary tables to archive tables
 */
function archiveOldLogs($conn) {
    $results = [];

    // Archive auth_logs older than 90 days
    try {
        // Insert into archive
        $query = "
            INSERT INTO auth_logs_archive
            SELECT * FROM auth_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND (archived = FALSE OR archived IS NULL)
        ";
        $conn->query($query);
        $archived_auth = $conn->affected_rows;

        // Mark as archived
        $conn->query("
            UPDATE auth_logs
            SET archived = TRUE
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");

        // Delete from primary table
        $conn->query("
            DELETE FROM auth_logs
            WHERE archived = TRUE
        ");

        $results['auth_logs_archived'] = $archived_auth;
    } catch (Exception $e) {
        error_log("Error archiving auth_logs: " . $e->getMessage());
        $results['auth_logs_archived'] = 0;
        $results['auth_logs_error'] = $e->getMessage();
    }

    // Archive sync_sessions older than 90 days
    try {
        $query = "
            INSERT INTO sync_sessions_archive
            SELECT * FROM sync_sessions
            WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND (archived = FALSE OR archived IS NULL)
        ";
        $conn->query($query);
        $archived_sync = $conn->affected_rows;

        $conn->query("
            UPDATE sync_sessions
            SET archived = TRUE
            WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");

        $conn->query("
            DELETE FROM sync_sessions
            WHERE archived = TRUE
        ");

        $results['sync_sessions_archived'] = $archived_sync;
    } catch (Exception $e) {
        error_log("Error archiving sync_sessions: " . $e->getMessage());
        $results['sync_sessions_archived'] = 0;
        $results['sync_sessions_error'] = $e->getMessage();
    }

    // Archive activity_logs older than 90 days
    try {
        $query = "
            INSERT INTO activity_logs_archive
            SELECT * FROM activity_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND (archived = FALSE OR archived IS NULL)
        ";
        $conn->query($query);
        $archived_activity = $conn->affected_rows;

        $conn->query("
            UPDATE activity_logs
            SET archived = TRUE
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");

        $conn->query("
            DELETE FROM activity_logs
            WHERE archived = TRUE
        ");

        $results['activity_logs_archived'] = $archived_activity;
    } catch (Exception $e) {
        error_log("Error archiving activity_logs: " . $e->getMessage());
        $results['activity_logs_archived'] = 0;
        $results['activity_logs_error'] = $e->getMessage();
    }

    return $results;
}

/**
 * Delete archived logs older than 3 years
 */
function deleteOldArchivedLogs($conn) {
    $results = [];

    // Delete auth_logs_archive older than 3 years
    try {
        $conn->query("
            DELETE FROM auth_logs_archive
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 YEAR)
        ");
        $results['auth_logs_deleted'] = $conn->affected_rows;
    } catch (Exception $e) {
        error_log("Error deleting old auth_logs_archive: " . $e->getMessage());
        $results['auth_logs_deleted'] = 0;
        $results['auth_logs_delete_error'] = $e->getMessage();
    }

    // Delete sync_sessions_archive older than 3 years
    try {
        $conn->query("
            DELETE FROM sync_sessions_archive
            WHERE started_at < DATE_SUB(NOW(), INTERVAL 3 YEAR)
        ");
        $results['sync_sessions_deleted'] = $conn->affected_rows;
    } catch (Exception $e) {
        error_log("Error deleting old sync_sessions_archive: " . $e->getMessage());
        $results['sync_sessions_deleted'] = 0;
        $results['sync_sessions_delete_error'] = $e->getMessage();
    }

    // Delete activity_logs_archive older than 3 years
    try {
        $conn->query("
            DELETE FROM activity_logs_archive
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 YEAR)
        ");
        $results['activity_logs_deleted'] = $conn->affected_rows;
    } catch (Exception $e) {
        error_log("Error deleting old activity_logs_archive: " . $e->getMessage());
        $results['activity_logs_deleted'] = 0;
        $results['activity_logs_delete_error'] = $e->getMessage();
    }

    return $results;
}

/**
 * Get storage statistics (how many records in hot vs cold)
 */
function getStorageStatistics($conn) {
    $stats = [];

    // Auth logs statistics
    $result = $conn->query("SELECT COUNT(*) as count FROM auth_logs");
    $stats['auth_logs_hot'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM auth_logs_archive");
    $stats['auth_logs_cold'] = $result->fetch_assoc()['count'];

    // Sync sessions statistics
    $result = $conn->query("SELECT COUNT(*) as count FROM sync_sessions");
    $stats['sync_sessions_hot'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM sync_sessions_archive");
    $stats['sync_sessions_cold'] = $result->fetch_assoc()['count'];

    // Activity logs statistics
    $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs");
    $stats['activity_logs_hot'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs_archive");
    $stats['activity_logs_cold'] = $result->fetch_assoc()['count'];

    // Calculate totals
    $stats['total_hot'] = $stats['auth_logs_hot'] + $stats['sync_sessions_hot'] + $stats['activity_logs_hot'];
    $stats['total_cold'] = $stats['auth_logs_cold'] + $stats['sync_sessions_cold'] + $stats['activity_logs_cold'];
    $stats['grand_total'] = $stats['total_hot'] + $stats['total_cold'];

    return $stats;
}

/**
 * Optimize archive tables (run after archival to rebuild indexes)
 */
function optimizeArchiveTables($conn) {
    $tables = ['auth_logs_archive', 'sync_sessions_archive', 'activity_logs_archive'];

    foreach ($tables as $table) {
        try {
            $conn->query("OPTIMIZE TABLE $table");
            error_log("Optimized table: $table");
        } catch (Exception $e) {
            error_log("Error optimizing $table: " . $e->getMessage());
        }
    }
}
