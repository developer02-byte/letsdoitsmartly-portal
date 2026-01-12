<?php
/**
 * Activity Logs AJAX Handler
 * Handles data retrieval for unified activity logs page with 3 tabs
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';

// Check if sync_helpers exists before loading
if (file_exists('includes/sync_helpers.php')) {
    require_once 'includes/sync_helpers.php';
}

requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_login_logs':
            getLoginLogs();
            break;
        case 'get_sync_logs':
            getSyncLogs();
            break;
        case 'get_sync_details':
            getSyncDetails();
            break;
        case 'get_admin_logs':
            getAdminActions();
            break;
        default:
            jsonResponse(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Activity logs handler error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Get login/logout logs - ONLY authentication events
 * Shows all users for super_admin, own logs for client_admin
 */
function getLoginLogs() {
    global $conn;

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    // Role-based filtering - Super admin sees all, client admin sees only own logs
    if (!isSuperAdmin()) {
        $where[] = "al.user_id = ?";
        $params[] = getCurrentUserId();
        $types .= 'i';
    } elseif (isset($_GET['user']) && $_GET['user'] !== '') {
        $where[] = "al.user_id = ?";
        $params[] = intval($_GET['user']);
        $types .= 'i';
    }

    // Auth type filter (login, logout, failed_login)
    if (isset($_GET['auth_type']) && $_GET['auth_type'] !== '') {
        $where[] = "al.auth_type = ?";
        $params[] = $_GET['auth_type'];
        $types .= 's';
    }

    // Status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = "al.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    // Date range (use al. prefix for auth_logs table)
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $where[] = "al.created_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
        $types .= 's';
    }
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $where[] = "al.created_at <= ?";
        $params[] = $_GET['date_to'] . ' 23:59:59';
        $types .= 's';
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT al.*, u.name as user_name, u.role as user_role_name
            FROM auth_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $where_clause
            ORDER BY al.created_at DESC
            LIMIT $per_page OFFSET $offset";

    $count_sql = "SELECT COUNT(*) as total FROM auth_logs al LEFT JOIN users u ON al.user_id = u.id $where_clause";

    // Execute queries
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(['success' => false, 'error' => 'Query error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            jsonResponse(['success' => false, 'error' => 'Query error: ' . $conn->error]);
            return;
        }
        $count_result = $conn->query($count_sql);
        $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    }

    // Build HTML
    $html = '';
    if ($result && $result->num_rows > 0) {
        $html .= '<div class="table-responsive"><table class="table table-hover">';
        $html .= '<thead class="table-light"><tr>';
        $html .= '<th>Time</th><th>User</th><th>Role</th><th>Type</th><th>Status</th>';
        $html .= '<th>IP Address</th><th>Device</th><th>Duration</th>';
        $html .= '</tr></thead><tbody>';

        while ($row = $result->fetch_assoc()) {
            $status_badge = $row['status'] === 'success' ? 'success' : 'danger';
            $type_badge = $row['auth_type'] === 'login' ? 'primary' : ($row['auth_type'] === 'logout' ? 'secondary' : 'warning');
            $role_badge = ($row['user_role_name'] ?? '') === 'super_admin' ? 'dark' : 'info';

            $html .= '<tr>';
            $html .= '<td><small>' . formatDateTime($row['created_at']) . '</small></td>';
            $html .= '<td>' . htmlspecialchars($row['user_name'] ?? $row['email_attempted']) . '</td>';
            $html .= '<td><span class="badge bg-' . $role_badge . '">' . ucfirst(str_replace('_', ' ', $row['user_role_name'] ?? '-')) . '</span></td>';
            $html .= '<td><span class="badge bg-' . $type_badge . '">' . ucfirst(str_replace('_', ' ', $row['auth_type'])) . '</span></td>';
            $html .= '<td><span class="badge bg-' . $status_badge . '">' . ucfirst($row['status']) . '</span></td>';
            $html .= '<td><small class="text-muted">' . htmlspecialchars($row['ip_address'] ?? '-') . '</small></td>';

            $device = ($row['device_type'] ?? 'Unknown') . ' / ' . ($row['browser'] ?? 'Unknown');
            $html .= '<td><small class="text-muted">' . htmlspecialchars($device) . '</small></td>';

            if ($row['session_duration_seconds']) {
                $duration = formatDuration($row['session_duration_seconds']);
                $html .= '<td><span class="badge bg-info">' . $duration . '</span></td>';
            } else {
                $html .= '<td><span class="text-muted">-</span></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    } else {
        $html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No authentication logs found.</div>';
    }

    jsonResponse([
        'success' => true,
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page
    ]);
}

/**
 * Get sync sessions logs - Auto-fixes stuck "in_progress" sessions
 */
function getSyncLogs() {
    global $conn;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
        return;
    }

    // First, fix any stuck "in_progress" syncs older than 10 minutes
    $conn->query("UPDATE sync_sessions
                  SET status = 'failed',
                      error_summary = 'Marked as failed - sync was stuck in progress',
                      completed_at = NOW(),
                      duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                  WHERE status = 'in_progress'
                  AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Check if sync_sessions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'sync_sessions'");
    if (!$table_check || $table_check->num_rows === 0) {
        jsonResponse([
            'success' => true,
            'html' => '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Sync sessions tracking not yet configured.</div>',
            'total' => 0,
            'page' => 1,
            'per_page' => $per_page,
            'has_active_syncs' => false
        ]);
        return;
    }

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    if (isset($_GET['sync_type']) && $_GET['sync_type'] !== '') {
        $where[] = "sync_type = ?";
        $params[] = $_GET['sync_type'];
        $types .= 's';
    }

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $where[] = "started_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
        $types .= 's';
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT * FROM sync_sessions $where_clause ORDER BY started_at DESC LIMIT $per_page OFFSET $offset";
    $count_sql = "SELECT COUNT(*) as total FROM sync_sessions $where_clause";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $result = $conn->query($sql);
        $count_result = $conn->query($count_sql);
        $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    }

    // Build HTML
    $html = '';
    $has_active_syncs = false;

    if ($result && $result->num_rows > 0) {
        $html .= '<div class="table-responsive"><table class="table table-hover">';
        $html .= '<thead class="table-light"><tr>';
        $html .= '<th>Started</th><th>Type</th><th>Initiated By</th><th>Status</th>';
        $html .= '<th>Duration</th><th>Domains</th><th>Users</th><th>Actions</th>';
        $html .= '</tr></thead><tbody>';

        while ($row = $result->fetch_assoc()) {
            $status_class = [
                'in_progress' => 'warning',
                'completed' => 'success',
                'failed' => 'danger',
                'partial' => 'info'
            ][$row['status']] ?? 'secondary';

            if ($row['status'] === 'in_progress') {
                $has_active_syncs = true;
            }

            $html .= '<tr>';
            $html .= '<td><small>' . formatDateTime($row['started_at']) . '</small></td>';
            $html .= '<td><span class="badge bg-primary">' . ucfirst($row['sync_type']) . '</span></td>';
            $html .= '<td>' . htmlspecialchars($row['initiated_by_name'] ?? 'System') . '</td>';
            $html .= '<td><span class="badge bg-' . $status_class . '">' . ucfirst(str_replace('_', ' ', $row['status'])) . '</span></td>';

            $html .= '<td>' . ($row['duration_seconds'] ? formatDuration($row['duration_seconds']) : '-') . '</td>';
            $html .= '<td>' . ($row['total_domains_processed'] ?? 0) . '</td>';
            $html .= '<td>' . ($row['total_users_imported'] ?? 0) . ' new, ' . ($row['total_users_updated'] ?? 0) . ' updated</td>';
            $html .= '<td><button class="btn btn-sm btn-outline-primary view-details" data-sync-id="' . $row['id'] . '"><i class="bi bi-eye"></i></button></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    } else {
        $html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No sync sessions found.</div>';
    }

    jsonResponse([
        'success' => true,
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'has_active_syncs' => $has_active_syncs
    ]);
}

/**
 * Get sync session details (drill-down)
 */
function getSyncDetails() {
    global $conn;

    if (!isSuperAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $sync_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$sync_id) {
        jsonResponse(['success' => false, 'error' => 'Invalid sync ID']);
        return;
    }

    // Get sync session
    $stmt = $conn->prepare("SELECT * FROM sync_sessions WHERE id = ?");
    $stmt->bind_param('i', $sync_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        jsonResponse(['success' => false, 'error' => 'Sync session not found']);
        return;
    }

    // Get import logs for this session
    $import_logs = [];
    $logs_result = $conn->query("SELECT il.*, d.domain_name
        FROM import_logs il
        LEFT JOIN domains d ON il.domain_id = d.id
        WHERE il.sync_session_id = $sync_id
        ORDER BY il.created_at ASC");

    if ($logs_result) {
        while ($log = $logs_result->fetch_assoc()) {
            $import_logs[] = $log;
        }
    }

    // Build HTML modal content
    $status_class = [
        'in_progress' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        'partial' => 'info'
    ][$session['status']] ?? 'secondary';

    $html = '<div class="modal-header">';
    $html .= '<h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Sync Session #' . $sync_id . '</h5>';
    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
    $html .= '</div>';

    $html .= '<div class="modal-body">';

    // Summary card
    $html .= '<div class="card mb-3">';
    $html .= '<div class="card-body">';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<p><strong>Type:</strong> <span class="badge bg-primary">' . ucfirst($session['sync_type']) . '</span></p>';
    $html .= '<p><strong>Initiated By:</strong> ' . htmlspecialchars($session['initiated_by_name'] ?? 'System') . '</p>';
    $html .= '<p><strong>Started:</strong> ' . formatDateTime($session['started_at']) . '</p>';
    $html .= '</div>';
    $html .= '<div class="col-md-6">';
    $html .= '<p><strong>Status:</strong> <span class="badge bg-' . $status_class . '">' . ucfirst($session['status']) . '</span></p>';
    if ($session['duration_seconds']) {
        $html .= '<p><strong>Duration:</strong> ' . formatDuration($session['duration_seconds']) . '</p>';
    }
    if ($session['completed_at']) {
        $html .= '<p><strong>Completed:</strong> ' . formatDateTime($session['completed_at']) . '</p>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // Per-domain results
    if (!empty($import_logs)) {
        $html .= '<h6 class="mt-3"><i class="bi bi-globe me-2"></i>Per-Domain Results (' . count($import_logs) . ' domains)</h6>';
        $html .= '<div class="table-responsive"><table class="table table-sm table-striped">';
        $html .= '<thead><tr><th>Domain</th><th>Status</th><th>Fetched</th><th>Imported</th><th>Skipped</th><th>Errors</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($import_logs as $log) {
            $log_status_badge = ($log['status'] ?? 'unknown') === 'completed' ? 'success' : 'warning';
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($log['domain_name'] ?? 'Unknown') . '</td>';
            $html .= '<td><span class="badge bg-' . $log_status_badge . '">' . ($log['status'] ?? '-') . '</span></td>';
            $html .= '<td>' . ($log['total_fetched'] ?? 0) . '</td>';
            $html .= '<td>' . ($log['total_imported'] ?? 0) . '</td>';
            $html .= '<td>' . ($log['total_skipped'] ?? 0) . '</td>';
            $html .= '<td>' . ($log['total_errors'] ?? 0) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    }

    // Error summary if any
    if ($session['error_summary']) {
        $html .= '<div class="alert alert-warning mt-3">';
        $html .= '<h6><i class="bi bi-exclamation-triangle me-2"></i>Errors</h6>';
        $html .= '<pre class="mb-0" style="white-space: pre-wrap;">' . htmlspecialchars($session['error_summary']) . '</pre>';
        $html .= '</div>';
    }

    $html .= '</div>';

    jsonResponse(['success' => true, 'html' => $html]);
}

/**
 * Get admin actions logs - EXCLUDES login/logout events
 * Shows: domain assignments, user management, Google Workspace actions
 */
function getAdminActions() {
    global $conn;

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    // ALWAYS exclude login/logout actions
    $where[] = "(al.action NOT LIKE '%logged in%' AND al.action NOT LIKE '%logged out%' AND al.action NOT LIKE '%login%' AND al.action NOT LIKE '%logout%')";

    // Also exclude auth and sync categories (sync shown in Sync Status tab)
    $where[] = "(al.action_category IS NULL OR al.action_category NOT IN ('auth', 'sync'))";

    // Role-based filtering for Admin Actions
    // Super Admin sees all, Client Admin sees actions on their assigned domains
    if (!isSuperAdmin()) {
        // Get client admin's allowed domain names
        $allowed_domains = $_SESSION['allowed_domains'] ?? [];
        if (!empty($allowed_domains)) {
            $domain_list = array_values($allowed_domains);
            $placeholders = implode(',', array_fill(0, count($domain_list), '?'));
            $where[] = "(al.target_domain IN ($placeholders) OR al.user_id = ?)";
            foreach ($domain_list as $domain_name) {
                $params[] = $domain_name;
                $types .= 's';
            }
            $params[] = getCurrentUserId();
            $types .= 'i';
        } else {
            // No domains assigned, only show own actions
            $where[] = "al.user_id = ?";
            $params[] = getCurrentUserId();
            $types .= 'i';
        }
    } elseif (isset($_GET['user']) && $_GET['user'] !== '') {
        $where[] = "al.user_id = ?";
        $params[] = intval($_GET['user']);
        $types .= 'i';
    }

    // Category filter
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $where[] = "al.action_category = ?";
        $params[] = $_GET['category'];
        $types .= 's';
    }

    // Date range (use al. prefix for activity_logs table)
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $where[] = "al.created_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
        $types .= 's';
    }
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $where[] = "al.created_at <= ?";
        $params[] = $_GET['date_to'] . ' 23:59:59';
        $types .= 's';
    }

    // Search
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $where[] = "(al.action LIKE ? OR al.target_email LIKE ? OR al.target_domain LIKE ?)";
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= 'sss';
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT al.*, u.name as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $where_clause
            ORDER BY al.created_at DESC
            LIMIT $per_page OFFSET $offset";

    $count_sql = "SELECT COUNT(*) as total FROM activity_logs al $where_clause";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(['success' => false, 'error' => 'Query error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            jsonResponse(['success' => false, 'error' => 'Query error: ' . $conn->error]);
            return;
        }
        $count_result = $conn->query($count_sql);
        $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    }

    // Build HTML
    $html = '';
    if ($result && $result->num_rows > 0) {
        $html .= '<div class="table-responsive"><table class="table table-hover">';
        $html .= '<thead class="table-light"><tr>';
        $html .= '<th>Time</th><th>User</th><th>Action</th><th>Target</th><th>Details</th>';
        $html .= '</tr></thead><tbody>';

        while ($row = $result->fetch_assoc()) {
            // Detect category from action text if not set
            $action_category = $row['action_category'];
            if (empty($action_category)) {
                $action_category = detectActionCategoryFromText($row['action']);
            }

            $category_badge = [
                'email' => 'primary',
                'user' => 'success',
                'domain' => 'info',
                'alias' => 'warning',
                'sync' => 'dark',
                'settings' => 'secondary',
                'other' => 'secondary'
            ][$action_category] ?? 'secondary';

            $html .= '<tr>';
            $html .= '<td><small>' . formatDateTime($row['created_at']) . '</small></td>';
            $html .= '<td><small>' . htmlspecialchars($row['user_name'] ?? $row['user_role'] ?? '-') . '</small></td>';
            $html .= '<td><span class="badge bg-' . $category_badge . ' text-white">' . htmlspecialchars($row['action']) . '</span></td>';

            $target = $row['target_email'] ?? $row['target_domain'] ?? '-';
            $html .= '<td><small class="text-muted">' . htmlspecialchars($target) . '</small></td>';

            if ($row['changes_made']) {
                $html .= '<td><button class="btn btn-sm btn-outline-secondary view-changes" data-changes=\'' . htmlspecialchars($row['changes_made'], ENT_QUOTES) . '\'><i class="bi bi-eye"></i> View</button></td>';
            } else {
                $html .= '<td><span class="text-muted">-</span></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    } else {
        $html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No admin actions found.</div>';
    }

    jsonResponse([
        'success' => true,
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page
    ]);
}

/**
 * Helper: Format duration in seconds to human readable
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $mins . 'm';
    }
}

/**
 * Helper: Detect action category from action text (for legacy entries)
 * Order matters - more specific patterns first
 */
function detectActionCategoryFromText($action) {
    $action_lower = strtolower($action);

    // Check sync FIRST (before domain, since "sync completed: X domains" contains "domain")
    if (strpos($action_lower, 'sync') !== false || strpos($action_lower, 'import') !== false) {
        return 'sync';
    }
    if (strpos($action_lower, 'alias') !== false) {
        return 'alias';
    }
    if (strpos($action_lower, 'email') !== false || strpos($action_lower, 'password') !== false) {
        return 'email';
    }
    if (strpos($action_lower, 'domain') !== false || strpos($action_lower, 'assigned') !== false) {
        return 'domain';
    }
    if (strpos($action_lower, 'user') !== false || strpos($action_lower, 'created user') !== false) {
        return 'user';
    }
    if (strpos($action_lower, 'setting') !== false || strpos($action_lower, 'profile') !== false) {
        return 'settings';
    }

    return 'other';
}
