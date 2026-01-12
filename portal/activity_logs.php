<?php
/**
 * Activity Logs Page
 * Unified Email Management Portal
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';

requireLogin();

$page_title = 'Activity Logs';

// Get filter parameters
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query based on role
$where_conditions = [];
$params = [];
$param_types = '';

// Role-based filtering
if (!isSuperAdmin()) {
    // Client admin only sees their own activity
    $user_id = getCurrentUserId();
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_id;
    $param_types .= 'i';
}

// User filter (super admin only)
if (isSuperAdmin() && $filter_user > 0) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $filter_user;
    $param_types .= 'i';
}

// Date filter
if ($filter_date) {
    $where_conditions[] = "DATE(al.created_at) = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

// Search filter
if ($search) {
    $where_conditions[] = "(al.action LIKE ? OR al.target_email LIKE ? OR al.target_domain LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs al $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_logs = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_logs / $per_page);

// Get logs
$sql = "
    SELECT al.*, u.name as user_name, u.email as user_email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC
    LIMIT $per_page OFFSET $offset
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($sql);
}

// Get users for filter (super admin only)
if (isSuperAdmin()) {
    $users = $conn->query("SELECT id, name, email FROM users ORDER BY name");
} else {
    $users = false;
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-clock-history"></i> Activity Logs</h2>
        <span class="text-muted"><?php echo number_format($total_logs); ?> total entries</span>
    </div>

    <!-- Filters -->
    <div class="content-card mb-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <?php if (isSuperAdmin()): ?>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user" class="form-select">
                        <option value="">All Users</option>
                        <?php if ($users): while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($u['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo sanitize($filter_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search actions..."
                           value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="activity_logs.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if ($logs && $logs->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                    <br>
                                    <span class="text-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></span>
                                </small>
                            </td>
                            <td>
                                <?php if ($log['user_name']): ?>
                                <strong><?php echo sanitize($log['user_name']); ?></strong>
                                <br><small class="text-muted"><?php echo sanitize($log['user_email']); ?></small>
                                <?php else: ?>
                                <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo sanitize($log['action']); ?>
                            </td>
                            <td>
                                <?php if ($log['target_email']): ?>
                                <span class="badge bg-info"><?php echo sanitize($log['target_email']); ?></span>
                                <?php elseif ($log['target_domain']): ?>
                                <span class="badge bg-secondary"><?php echo sanitize($log['target_domain']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo sanitize($log['ip_address'] ?? '-'); ?></small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&user=<?php echo $filter_user; ?>&date=<?php echo urlencode($filter_date); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1&user=<?php echo $filter_user; ?>&date=<?php echo urlencode($filter_date); ?>&search=<?php echo urlencode($search); ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&user=<?php echo $filter_user; ?>&date=<?php echo urlencode($filter_date); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&user=<?php echo $filter_user; ?>&date=<?php echo urlencode($filter_date); ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
                    </li>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&user=<?php echo $filter_user; ?>&date=<?php echo urlencode($filter_date); ?>&search=<?php echo urlencode($search); ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <p class="text-center text-muted">
                Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_logs); ?>
                of <?php echo number_format($total_logs); ?> entries
            </p>
            <?php endif; ?>

            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-clock-history text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">No Activity Logs Found</h5>
                <p class="text-muted">
                    <?php if ($search || $filter_user || $filter_date): ?>
                    No logs match your filters.
                    <a href="activity_logs.php">Clear filters</a>
                    <?php else: ?>
                    Activity will appear here as you use the portal.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
