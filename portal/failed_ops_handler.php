<?php
/**
 * Failed Operations Handler
 * AJAX endpoints for managing failed operations
 * Super Admin only
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/error_recovery.php';
require_once 'includes/google_functions.php';

requireLogin();

// Super admin only
if (!isSuperAdmin()) {
    jsonResponse(['success' => false, 'error' => 'Access denied']);
}

// Verify CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid security token']);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'auto_resolve':
        autoResolve();
        break;
    case 'manual_resolve':
        manualResolve();
        break;
    case 'mark_failed':
        markFailed();
        break;
    case 'auto_resolve_all':
        autoResolveAll();
        break;
    case 'consistency_check':
        consistencyCheck();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Attempt auto-resolve on a single operation
 */
function autoResolve() {
    global $conn;

    $op_id = intval($_POST['op_id'] ?? 0);
    if ($op_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid operation ID']);
    }

    $result = attemptAutoResolve($conn, $op_id);
    jsonResponse($result);
}

/**
 * Mark operation as manually resolved
 */
function manualResolve() {
    global $conn;

    $op_id = intval($_POST['op_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($op_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid operation ID']);
    }

    if (empty($notes)) {
        $notes = 'Manually resolved by admin';
    }

    $success = manuallyResolveOperation($conn, $op_id, $notes);

    if ($success) {
        logActivity($conn, "Manually resolved failed operation #$op_id");
        jsonResponse(['success' => true, 'message' => 'Operation marked as resolved']);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to update operation']);
    }
}

/**
 * Mark operation as permanently failed
 */
function markFailed() {
    global $conn;

    $op_id = intval($_POST['op_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($op_id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid operation ID']);
    }

    if (empty($notes)) {
        $notes = 'Marked as permanently failed by admin';
    }

    $success = markPermanentlyFailed($conn, $op_id, $notes);

    if ($success) {
        logActivity($conn, "Marked failed operation #$op_id as permanently failed");
        jsonResponse(['success' => true, 'message' => 'Operation marked as failed']);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to update operation']);
    }
}

/**
 * Auto-resolve all pending operations
 */
function autoResolveAll() {
    global $conn;

    $results = processFailedOperations($conn, 100); // Process up to 100

    logActivity($conn, sprintf(
        "Bulk auto-resolve: %d processed, %d resolved, %d failed",
        $results['processed'],
        $results['resolved'],
        $results['failed']
    ));

    jsonResponse([
        'success' => true,
        'processed' => $results['processed'],
        'resolved' => $results['resolved'],
        'failed' => $results['failed']
    ]);
}

/**
 * Run consistency check across all domains
 */
function consistencyCheck() {
    global $conn;

    $results = [];

    // Get all active domains
    $domains = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active'");

    while ($domain = $domains->fetch_assoc()) {
        $detection = detectDataInconsistencies($conn, $domain['id'], $domain['domain_name']);

        if ($detection['success']) {
            $results[] = [
                'domain' => $domain['domain_name'],
                'orphan_emails' => $detection['summary']['orphan_emails'],
                'stale_aliases' => $detection['summary']['stale_aliases'],
                'missing_aliases' => $detection['summary']['missing_aliases'],
                'status_mismatches' => $detection['summary']['status_mismatches'],
                'total_issues' => $detection['summary']['total_issues']
            ];
        } else {
            $results[] = [
                'domain' => $domain['domain_name'],
                'error' => $detection['error']
            ];
        }
    }

    logActivity($conn, "Ran consistency check across " . count($results) . " domains");

    jsonResponse([
        'success' => true,
        'results' => $results
    ]);
}
