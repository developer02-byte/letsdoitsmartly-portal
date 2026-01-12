<?php
/**
 * Cron Sync Endpoint
 * Automated background sync triggered by cron job
 *
 * Cron: Every 5 min - curl "https://letsdoitsmartly.com/portal/cron_sync.php?key=SECRET_KEY"
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/google_functions.php';
require_once 'includes/error_recovery.php';

// Set JSON response header
header('Content-Type: application/json');

// Verify cron key
$provided_key = $_GET['key'] ?? '';
$valid_key = $cron_config['secret_key'] ?? '';

if (empty($valid_key) || $provided_key !== $valid_key) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing cron key']);
    exit;
}

// Set longer execution time for large syncs
set_time_limit(600); // 10 minutes

// Get lock timeout from config
$lock_timeout = $cron_config['lock_timeout_minutes'] ?? 10;

// Try to acquire sync lock
$lock_result = acquireSyncLock($conn, $lock_timeout, 'cron');

if (!$lock_result['success']) {
    echo json_encode([
        'success' => false,
        'error' => 'Sync already in progress',
        'locked_by' => $lock_result['locked_by'] ?? 'unknown',
        'locked_at' => $lock_result['locked_at'] ?? null
    ]);
    exit;
}

// Perform full self-healing sync
try {
    $start_time = microtime(true);

    // Create sync session for tracking
    require_once 'includes/sync_helpers.php';
    $sync_session_id = createSyncSession($conn, [
        'sync_type' => 'cron',
        'initiated_by' => null,
        'initiated_by_name' => 'System (Cron Job)'
    ]);

    // Use enhanced self-healing sync instead of basic fullGoogleSync
    $result = fullSelfHealingSync($conn);
    $sync_duration = round(microtime(true) - $start_time, 2);

    // Update sync session with results
    if (isset($result['stats'])) {
        $result['duration'] = $sync_duration;
        updateSyncSession($conn, $sync_session_id, $result['stats']);
    }

    // Process pending failed operations
    $recovery_results = processFailedOperations($conn, 20); // Process up to 20 pending ops

    // Release lock
    releaseSyncLock($conn);

    if ($result['success']) {
        $stats = $result['stats'];

        // Record last sync completion time
        $conn->query("UPDATE sync_locks SET last_sync_completed = NOW() WHERE id = 1");

        // Log the sync
        $log_message = sprintf(
            "Cron sync completed in %ss: %d domains, %d users (%d imported, %d updated), aliases (+%d/-%d), healing (%d orphans, %d status)",
            $sync_duration,
            $stats['domains']['total'],
            $stats['users']['total'],
            $stats['users']['imported'],
            $stats['users']['updated'],
            $stats['aliases']['added'],
            $stats['aliases']['removed'],
            $stats['healing']['orphans_removed'],
            $stats['healing']['status_mismatches_fixed']
        );

        // Simple activity log (if function exists)
        if (function_exists('logActivity')) {
            logActivity($conn, $log_message);
        } else {
            error_log($log_message);
        }

        // Include recovery stats in response
        echo json_encode([
            'success' => true,
            'message' => 'Cron sync completed with self-healing',
            'duration_seconds' => $sync_duration,
            'stats' => $stats,
            'recovery' => $recovery_results
        ]);
    } else {
        error_log("Cron sync failed: " . ($result['error'] ?? 'Unknown error'));
        echo json_encode($result);
    }

} catch (Exception $e) {
    // Ensure lock is released even on error
    releaseSyncLock($conn);

    error_log("Cron sync exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Sync failed: ' . $e->getMessage()
    ]);
}
