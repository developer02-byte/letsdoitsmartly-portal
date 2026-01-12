<?php
/**
 * Cron Job: Archive Old Activity Logs
 * Hot/Cold Storage Management
 *
 * Schedule: Run monthly on 1st at 2 AM
 * Crontab: 0 2 1 * * curl "https://letsdoitsmartly.com/portal/cron_archive_logs.php?secret=YOUR_SECRET_KEY"
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/log_archival.php';

// Authenticate cron request
$cron_secret = $_GET['secret'] ?? '';
if ($cron_secret !== $cron_config['secret_key']) {
    http_response_code(403);
    die('Unauthorized');
}

// Set execution time limit (archival can take a while)
set_time_limit(600); // 10 minutes

echo "===========================================\n";
echo "Activity Logs Archival Process\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

$start_time = microtime(true);

// Step 1: Get current storage statistics
echo "Step 1: Current Storage Statistics\n";
echo str_repeat('-', 50) . "\n";
$stats_before = getStorageStatistics($conn);
echo "Hot Storage (Primary Tables):\n";
echo "  Auth Logs: " . number_format($stats_before['auth_logs_hot']) . "\n";
echo "  Sync Sessions: " . number_format($stats_before['sync_sessions_hot']) . "\n";
echo "  Activity Logs: " . number_format($stats_before['activity_logs_hot']) . "\n";
echo "  Total: " . number_format($stats_before['total_hot']) . "\n\n";

echo "Cold Storage (Archive Tables):\n";
echo "  Auth Logs: " . number_format($stats_before['auth_logs_cold']) . "\n";
echo "  Sync Sessions: " . number_format($stats_before['sync_sessions_cold']) . "\n";
echo "  Activity Logs: " . number_format($stats_before['activity_logs_cold']) . "\n";
echo "  Total: " . number_format($stats_before['total_cold']) . "\n\n";

// Step 2: Archive logs older than 90 days
echo "Step 2: Archiving logs older than 90 days\n";
echo str_repeat('-', 50) . "\n";
$archive_results = archiveOldLogs($conn);
echo "Archived Records:\n";
echo "  Auth Logs: " . number_format($archive_results['auth_logs_archived']) . "\n";
echo "  Sync Sessions: " . number_format($archive_results['sync_sessions_archived']) . "\n";
echo "  Activity Logs: " . number_format($archive_results['activity_logs_archived']) . "\n";

if (isset($archive_results['auth_logs_error'])) {
    echo "  ⚠ Auth Logs Error: " . $archive_results['auth_logs_error'] . "\n";
}
if (isset($archive_results['sync_sessions_error'])) {
    echo "  ⚠ Sync Sessions Error: " . $archive_results['sync_sessions_error'] . "\n";
}
if (isset($archive_results['activity_logs_error'])) {
    echo "  ⚠ Activity Logs Error: " . $archive_results['activity_logs_error'] . "\n";
}
echo "\n";

// Step 3: Delete archived logs older than 3 years
echo "Step 3: Deleting archived logs older than 3 years\n";
echo str_repeat('-', 50) . "\n";
$delete_results = deleteOldArchivedLogs($conn);
echo "Deleted Records:\n";
echo "  Auth Logs: " . number_format($delete_results['auth_logs_deleted']) . "\n";
echo "  Sync Sessions: " . number_format($delete_results['sync_sessions_deleted']) . "\n";
echo "  Activity Logs: " . number_format($delete_results['activity_logs_deleted']) . "\n";

if (isset($delete_results['auth_logs_delete_error'])) {
    echo "  ⚠ Auth Logs Delete Error: " . $delete_results['auth_logs_delete_error'] . "\n";
}
if (isset($delete_results['sync_sessions_delete_error'])) {
    echo "  ⚠ Sync Sessions Delete Error: " . $delete_results['sync_sessions_delete_error'] . "\n";
}
if (isset($delete_results['activity_logs_delete_error'])) {
    echo "  ⚠ Activity Logs Delete Error: " . $delete_results['activity_logs_delete_error'] . "\n";
}
echo "\n";

// Step 4: Optimize archive tables
echo "Step 4: Optimizing archive tables\n";
echo str_repeat('-', 50) . "\n";
optimizeArchiveTables($conn);
echo "Archive tables optimized\n\n";

// Step 5: Final storage statistics
echo "Step 5: Final Storage Statistics\n";
echo str_repeat('-', 50) . "\n";
$stats_after = getStorageStatistics($conn);
echo "Hot Storage (Primary Tables):\n";
echo "  Auth Logs: " . number_format($stats_after['auth_logs_hot']) . "\n";
echo "  Sync Sessions: " . number_format($stats_after['sync_sessions_hot']) . "\n";
echo "  Activity Logs: " . number_format($stats_after['activity_logs_hot']) . "\n";
echo "  Total: " . number_format($stats_after['total_hot']) . "\n\n";

echo "Cold Storage (Archive Tables):\n";
echo "  Auth Logs: " . number_format($stats_after['auth_logs_cold']) . "\n";
echo "  Sync Sessions: " . number_format($stats_after['sync_sessions_cold']) . "\n";
echo "  Activity Logs: " . number_format($stats_after['activity_logs_cold']) . "\n";
echo "  Total: " . number_format($stats_after['total_cold']) . "\n\n";

// Calculate execution time
$execution_time = round(microtime(true) - $start_time, 2);

echo "===========================================\n";
echo "Archival Process Summary\n";
echo "===========================================\n";
echo "Total Archived: " . number_format(
    $archive_results['auth_logs_archived'] +
    $archive_results['sync_sessions_archived'] +
    $archive_results['activity_logs_archived']
) . " records\n";
echo "Total Deleted: " . number_format(
    $delete_results['auth_logs_deleted'] +
    $delete_results['sync_sessions_deleted'] +
    $delete_results['activity_logs_deleted']
) . " records\n";
echo "Execution Time: {$execution_time}s\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n";

// Log to error_log for cron monitoring
error_log("Activity logs archival completed - Archived: " .
    ($archive_results['auth_logs_archived'] + $archive_results['sync_sessions_archived'] + $archive_results['activity_logs_archived']) .
    ", Deleted: " .
    ($delete_results['auth_logs_deleted'] + $delete_results['sync_sessions_deleted'] + $delete_results['activity_logs_deleted']) .
    ", Duration: {$execution_time}s"
);
