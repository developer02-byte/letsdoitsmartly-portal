<?php
/**
 * Sync Status API
 * Returns current sync status as JSON for AJAX polling
 */
define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');

$result = $conn->query("SELECT last_sync_completed, locked_at, locked_by FROM sync_locks WHERE id = 1");
$row = $result ? $result->fetch_assoc() : null;

$last_sync_time = $row['last_sync_completed'] ?? null;
$sync_in_progress = !empty($row['locked_at']);
$sync_locked_by = $row['locked_by'] ?? null;

// Calculate next sync (cron runs every 5 minutes at :00, :05, :10, etc.)
$sync_interval = 5;
$next_sync = null;
$next_sync_timestamp = null;

if ($last_sync_time || $sync_in_progress) {
    // Calculate next cron trigger (runs at :00, :05, :10, :15, etc.)
    $now = time();
    $current_min = (int)date('i', $now);
    $next_min = (int)(ceil($current_min / $sync_interval) * $sync_interval);

    if ($next_min >= 60) {
        $next_min = 0;
        $next_sync_timestamp = strtotime('+1 hour', strtotime(date('Y-m-d H:00:00', $now)));
    } else {
        $next_sync_timestamp = strtotime(date('Y-m-d H:') . sprintf('%02d:00', $next_min));
    }

    // If we're past that time, add interval
    if ($next_sync_timestamp <= $now) {
        $next_sync_timestamp += $sync_interval * 60;
    }

    $next_sync = date('H:i', $next_sync_timestamp);
}

echo json_encode([
    'success' => true,
    'syncing' => $sync_in_progress,
    'locked_by' => $sync_locked_by,
    'last_sync' => $last_sync_time,
    'last_sync_ago' => $last_sync_time ? timeAgo($last_sync_time) : null,
    'last_sync_formatted' => $last_sync_time ? date('d M H:i:s', strtotime($last_sync_time)) : null,
    'next_sync' => $next_sync,
    'next_sync_timestamp' => $next_sync_timestamp,
    'server_time' => date('H:i:s')
]);
