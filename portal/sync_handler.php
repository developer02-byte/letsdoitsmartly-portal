<?php
/**
 * Sync Handler
 * Handles Google Workspace sync operations via AJAX
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/google_functions.php';

requireLogin();

// Handle AJAX requests
header('Content-Type: application/json');

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'full_sync':
        fullSync();
        break;
    case 'sync_domain':
        syncDomain();
        break;
    case 'sync_all_domains':
        syncAllDomains();
        break;
    case 'check_sync_status':
        checkSyncStatus();
        break;
    case 'fetch_google_domains':
        fetchDomainsFromGoogle();
        break;
    case 'import_domains':
        importDomainsFromGoogle();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Full sync - domains, users, and aliases in one operation
 * Super Admin only
 */
function fullSync() {
    global $conn, $cron_config;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    }

    // Set longer execution time for large syncs
    set_time_limit(600); // 10 minutes

    // Try to acquire sync lock
    $lock_timeout = $cron_config['lock_timeout_minutes'] ?? 10;
    $lock_result = acquireSyncLock($conn, $lock_timeout, 'manual');

    if (!$lock_result['success']) {
        jsonResponse([
            'success' => false,
            'error' => 'Sync already in progress (started by ' . ($lock_result['locked_by'] ?? 'unknown') . ')',
            'locked_by' => $lock_result['locked_by'] ?? 'unknown',
            'locked_at' => $lock_result['locked_at'] ?? null
        ]);
    }

    $user_id = getCurrentUserId();

    // Create sync session for tracking
    require_once 'includes/sync_helpers.php';
    $sync_session_id = createSyncSession($conn, [
        'sync_type' => 'manual',
        'initiated_by' => $user_id,
        'initiated_by_name' => $_SESSION['name'] ?? 'Admin'
    ]);

    // Perform full sync
    try {
        $result = fullGoogleSync($conn);

        // Update sync session with results
        updateSyncSession($conn, $sync_session_id, $result);
    } catch (Exception $e) {
        // Update sync session with failure
        updateSyncSession($conn, $sync_session_id, [
            'status' => 'failed',
            'had_errors' => true,
            'error_summary' => $e->getMessage()
        ]);
        throw $e;
    } finally {
        // Always release lock
        releaseSyncLock($conn);
    }

    if ($result['success']) {
        $stats = $result['stats'];

        // Log the activity
        $message = sprintf(
            "Full sync completed: %d domains (%d new), %d users (%d imported, %d updated)",
            $stats['domains']['total'],
            $stats['domains']['new'],
            $stats['users']['total'],
            $stats['users']['imported'],
            $stats['users']['updated']
        );
        logActivity($conn, $message, null, null, [
            'category' => 'sync',
            'resource_type' => 'sync',
            'changes' => [
                'action' => 'full_sync',
                'stats' => $stats
            ]
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Full sync completed successfully',
            'stats' => $stats
        ]);
    } else {
        logActivity($conn, "Full sync failed: " . ($result['error'] ?? 'Unknown error'), null, null, [
            'category' => 'sync',
            'resource_type' => 'sync',
            'changes' => [
                'action' => 'full_sync_failed',
                'error' => $result['error'] ?? 'Unknown error'
            ]
        ]);
        jsonResponse($result);
    }
}

/**
 * Sync a single domain with Google Workspace
 */
function syncDomain() {
    global $conn;

    $domain_id = intval($_POST['domain_id'] ?? 0);

    if ($domain_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid domain ID']);
    }

    // Get domain info
    $stmt = $conn->prepare("SELECT id, domain_name, domain_owner_id FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $domain = $stmt->get_result()->fetch_assoc();

    if (!$domain) {
        jsonResponse(['success' => false, 'error' => 'Domain not found']);
    }

    // Check permission
    if (!isSuperAdmin()) {
        $allowed_ids = getAllowedDomainIds();
        if (!in_array($domain_id, $allowed_ids ?: [])) {
            jsonResponse(['success' => false, 'error' => 'Access denied']);
        }
    }

    // Create import log entry
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("
        INSERT INTO import_logs (domain_id, initiated_by, sync_type, status)
        VALUES (?, ?, 'manual', 'in_progress')
    ");
    $stmt->bind_param('ii', $domain_id, $user_id);
    $stmt->execute();
    $import_log_id = $conn->insert_id;

    // Perform sync
    $result = syncGoogleUsersToDatabase($domain_id, $domain['domain_name'], $conn);

    if ($result['success']) {
        $stats = $result['stats'];

        // Update import log
        $stmt = $conn->prepare("
            UPDATE import_logs
            SET status = 'completed',
                total_fetched = ?,
                total_imported = ?,
                total_skipped = ?,
                total_errors = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('iiiii', $stats['total_fetched'], $stats['imported'], $stats['updated'], $stats['errors'], $import_log_id);
        $stmt->execute();

        logActivity($conn, "Synced domain: {$domain['domain_name']}", null, $domain['domain_name'], [
            'category' => 'sync',
            'resource_type' => 'domain',
            'resource_id' => $domain_id,
            'changes' => [
                'action' => 'domain_sync',
                'domain_name' => $domain['domain_name'],
                'stats' => $stats
            ]
        ]);

        jsonResponse([
            'success' => true,
            'message' => "Sync completed for {$domain['domain_name']}",
            'stats' => $stats
        ]);
    } else {
        // Update import log with error
        $error_msg = $result['error'];
        $stmt = $conn->prepare("
            UPDATE import_logs
            SET status = 'failed', error_message = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('si', $error_msg, $import_log_id);
        $stmt->execute();

        jsonResponse(['success' => false, 'error' => $result['error']]);
    }
}

/**
 * Sync all domains (Super Admin only)
 */
function syncAllDomains() {
    global $conn;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    }

    // Get all active domains
    $domains = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active'");

    $results = [];
    $total_success = 0;
    $total_failed = 0;

    while ($domain = $domains->fetch_assoc()) {
        $result = syncGoogleUsersToDatabase($domain['id'], $domain['domain_name'], $conn);

        if ($result['success']) {
            $total_success++;
            $results[] = [
                'domain' => $domain['domain_name'],
                'success' => true,
                'stats' => $result['stats']
            ];
        } else {
            $total_failed++;
            $results[] = [
                'domain' => $domain['domain_name'],
                'success' => false,
                'error' => $result['error']
            ];
        }
    }

    logActivity($conn, "Synced all domains: $total_success success, $total_failed failed", null, null, [
        'category' => 'sync',
        'resource_type' => 'sync',
        'changes' => [
            'action' => 'sync_all_domains',
            'results' => [
                'success_count' => $total_success,
                'failed_count' => $total_failed,
                'domain_results' => $domain_results
            ]
        ]
    ]);

    jsonResponse([
        'success' => true,
        'message' => "Sync completed: $total_success success, $total_failed failed",
        'total_success' => $total_success,
        'total_failed' => $total_failed,
        'results' => $results
    ]);
}

/**
 * Check if domain needs sync (is stale)
 */
function checkSyncStatus() {
    global $conn, $sync_config;

    $domain_id = intval($_GET['domain_id'] ?? 0);

    if ($domain_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid domain ID']);
    }

    $stmt = $conn->prepare("SELECT domain_name, last_google_sync FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $domain = $stmt->get_result()->fetch_assoc();

    if (!$domain) {
        jsonResponse(['success' => false, 'error' => 'Domain not found']);
    }

    $last_sync = $domain['last_google_sync'];
    $is_stale = false;
    $seconds_since_sync = null;

    if ($last_sync) {
        $seconds_since_sync = time() - strtotime($last_sync);
        $is_stale = $seconds_since_sync > $sync_config['stale_threshold'];
    } else {
        $is_stale = true;
    }

    jsonResponse([
        'success' => true,
        'domain' => $domain['domain_name'],
        'last_sync' => $last_sync,
        'seconds_since_sync' => $seconds_since_sync,
        'is_stale' => $is_stale,
        'stale_threshold' => $sync_config['stale_threshold']
    ]);
}

/**
 * Fetch domains from Google Workspace (for adding new domains)
 */
function fetchDomainsFromGoogle() {
    global $conn;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    }

    $result = fetchGoogleDomains();

    if (!$result['success']) {
        jsonResponse($result);
    }

    // Get existing domains in database
    $existing = [];
    $db_domains = $conn->query("SELECT domain_name FROM domains");
    while ($d = $db_domains->fetch_assoc()) {
        $existing[] = strtolower($d['domain_name']);
    }

    // Mark which domains are already in database
    foreach ($result['domains'] as &$domain) {
        $domain['in_database'] = in_array(strtolower($domain['domain_name']), $existing);
    }

    jsonResponse($result);
}

/**
 * Get recent import logs for a domain
 */
function getImportLogs() {
    global $conn;

    $domain_id = intval($_GET['domain_id'] ?? 0);
    $limit = min(intval($_GET['limit'] ?? 10), 50);

    $stmt = $conn->prepare("
        SELECT il.*, u.name as initiated_by_name
        FROM import_logs il
        LEFT JOIN users u ON il.initiated_by = u.id
        WHERE il.domain_id = ?
        ORDER BY il.started_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $domain_id, $limit);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    jsonResponse(['success' => true, 'logs' => $logs]);
}

/**
 * Import domains from Google Workspace into database
 */
function importDomainsFromGoogle() {
    global $conn;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    }

    $domains = json_decode($_POST['domains'] ?? '[]', true);
    $domain_owner_id = intval($_POST['domain_owner_id'] ?? 0);

    if (empty($domains)) {
        jsonResponse(['success' => false, 'error' => 'No domains selected']);
    }

    if ($domain_owner_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Please select a domain owner']);
    }

    // Verify domain owner exists
    $stmt = $conn->prepare("SELECT id FROM domain_owners WHERE id = ?");
    $stmt->bind_param('i', $domain_owner_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['success' => false, 'error' => 'Domain owner not found']);
    }

    $imported = 0;
    $errors = [];

    foreach ($domains as $domain_name) {
        $domain_name = strtolower(trim($domain_name));

        // Skip if already exists
        $check = $conn->prepare("SELECT id FROM domains WHERE domain_name = ?");
        $check->bind_param('s', $domain_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            continue; // Already exists, skip
        }

        // Insert domain
        $insert = $conn->prepare("
            INSERT INTO domains (domain_name, domain_owner_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $insert->bind_param('si', $domain_name, $domain_owner_id);

        if ($insert->execute()) {
            $new_domain_id = $conn->insert_id;
            $imported++;
            logActivity($conn, "Imported domain from Google: $domain_name", null, $domain_name, [
                'category' => 'domain',
                'resource_type' => 'domain',
                'resource_id' => $new_domain_id,
                'changes' => [
                    'action' => 'import_from_google',
                    'domain' => [
                        'id' => $new_domain_id,
                        'domain_name' => $domain_name,
                        'is_primary' => $is_primary
                    ]
                ]
            ]);
        } else {
            $errors[] = $domain_name . ': ' . $conn->error;
        }
    }

    jsonResponse([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}
