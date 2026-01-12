<?php
/**
 * Dashboard
 * Unified Email Management Portal
 */

// DEBUG: Check session state before auth (access with ?debug=1)
if (isset($_GET['debug'])) {
    // Configure session BEFORE any output (same as auth.php)
    $custom_save_path = __DIR__ . '/sessions';
    if (is_dir($custom_save_path)) {
        session_save_path($custom_save_path);
    }

    session_name('portal_session');
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/portal/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    $session_result = @session_start();
    $session_error = error_get_last();

    // NOW we can output
    header('Content-Type: text/plain');

    echo "=== Session Debug ===\n";
    echo "PHP: " . phpversion() . "\n";
    echo "Custom save path: $custom_save_path\n";
    echo "Path exists: " . (is_dir($custom_save_path) ? 'YES' : 'NO') . "\n";
    echo "Path writable: " . (is_writable($custom_save_path) ? 'YES' : 'NO') . "\n";
    echo "Actual save path: " . session_save_path() . "\n\n";

    echo "Cookie: " . ($_COOKIE['portal_session'] ?? 'NOT SET') . "\n\n";

    echo "session_start(): " . ($session_result ? 'TRUE' : 'FALSE') . "\n";
    if ($session_error && strpos($session_error['message'] ?? '', 'session') !== false) {
        echo "Error: " . $session_error['message'] . "\n";
    }
    echo "session_id(): '" . session_id() . "'\n";
    echo "session_status(): " . session_status() . " (2=active)\n\n";

    echo "Session Variables:\n";
    print_r($_SESSION);
    exit;
}

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';
require_once 'includes/error_recovery.php';
require_once 'includes/google_functions.php';

// Require login
requireLogin();

$page_title = 'Dashboard';

// Get sync status
$last_sync_time = null;
$sync_in_progress = false;
$sync_locked_by = null;
$sync_result = $conn->query("SELECT last_sync_completed, locked_at, locked_by FROM sync_locks WHERE id = 1");
if ($sync_row = $sync_result->fetch_assoc()) {
    $last_sync_time = $sync_row['last_sync_completed'];
    $sync_in_progress = !empty($sync_row['locked_at']);
    $sync_locked_by = $sync_row['locked_by'];
}

// Get statistics based on role
if (isSuperAdmin()) {
    // Super Admin: Global stats
    $total_domains = $conn->query("SELECT COUNT(*) as count FROM domains")->fetch_assoc()['count'];
    $total_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts")->fetch_assoc()['count'];
    $active_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts WHERE status = 'active'")->fetch_assoc()['count'];
    $suspended_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts WHERE status = 'suspended'")->fetch_assoc()['count'];
    $total_aliases = $conn->query("SELECT COUNT(*) as count FROM email_aliases")->fetch_assoc()['count'];
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

    // Recent activity (last 10)
    $recent_activity = $conn->query("
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");

    // Get pending failed operations count
    $failed_ops_count = getPendingFailedOperationsCount($conn);

    // Get removed domains count
    $removed_domains_count = getRemovedDomainsCount($conn);

    // Domains overview
    $domains_overview = $conn->query("
        SELECT d.domain_name, d.status, d.last_google_sync,
               COUNT(DISTINCT e.id) as email_count,
               COUNT(DISTINCT ea.id) as alias_count
        FROM domains d
        LEFT JOIN email_accounts e ON d.id = e.domain_id
        LEFT JOIN email_aliases ea ON e.id = ea.email_account_id
        GROUP BY d.id
        ORDER BY email_count DESC
        LIMIT 5
    ");

} else {
    // Client Admin: Their domain stats only
    $domain_filter = getDomainFilterSQL('d.id');
    $email_domain_filter = getDomainFilterSQL('e.domain_id');
    $direct_email_filter = getDomainFilterSQL('domain_id');

    $total_domains = $conn->query("SELECT COUNT(*) as count FROM domains d WHERE $domain_filter")->fetch_assoc()['count'];
    $total_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts WHERE $direct_email_filter")->fetch_assoc()['count'];
    $active_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts WHERE $direct_email_filter AND status = 'active'")->fetch_assoc()['count'];
    $suspended_emails = $conn->query("SELECT COUNT(*) as count FROM email_accounts WHERE $direct_email_filter AND status = 'suspended'")->fetch_assoc()['count'];
    $total_aliases = $conn->query("
        SELECT COUNT(*) as count FROM email_aliases ea
        JOIN email_accounts e ON ea.email_account_id = e.id
        WHERE $email_domain_filter
    ")->fetch_assoc()['count'];
    $total_users = null; // Not shown for client admin

    // Recent activity (their own)
    $user_id = getCurrentUserId();
    $recent_activity = $conn->prepare("
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $recent_activity->bind_param('i', $user_id);
    $recent_activity->execute();
    $recent_activity = $recent_activity->get_result();

    // Their domains overview
    $allowed_ids = implode(',', array_map('intval', getAllowedDomainIds() ?: [0]));
    $domains_overview = $conn->query("
        SELECT d.domain_name, d.status, d.last_google_sync,
               COUNT(DISTINCT e.id) as email_count,
               COUNT(DISTINCT ea.id) as alias_count
        FROM domains d
        LEFT JOIN email_accounts e ON d.id = e.domain_id
        LEFT JOIN email_aliases ea ON e.id = ea.email_account_id
        WHERE d.id IN ($allowed_ids)
        GROUP BY d.id
        ORDER BY email_count DESC
    ");
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
        <div class="d-flex align-items-center gap-3">
            <?php if (isSuperAdmin()): ?>
            <?php
                // Calculate next sync (cron runs every 5 minutes)
                $sync_interval = 5;
                $next_sync = null;
                if ($last_sync_time) {
                    $last = strtotime($last_sync_time);
                    $next = $last + ($sync_interval * 60);
                    // If next sync is in the past, calculate from now
                    if ($next < time()) {
                        $mins = (int)date('i');
                        $next_min = ceil($mins / $sync_interval) * $sync_interval;
                        $next = strtotime(date('Y-m-d H:') . sprintf('%02d:00', $next_min % 60));
                        if ($next <= time()) $next += $sync_interval * 60;
                    }
                    $next_sync = date('H:i', $next);
                }
            ?>
            <span id="sync-status-badge" class="badge <?php echo $sync_in_progress ? 'bg-warning' : 'bg-success'; ?>"
                  title="<?php echo $last_sync_time ? 'Last: ' . date('d M H:i:s', strtotime($last_sync_time)) : 'Never synced'; ?>"
                  style="cursor: help;">
                <i id="sync-icon" class="bi <?php echo $sync_in_progress ? 'bi-arrow-repeat' : 'bi-cloud-check'; ?>"></i>
                <span id="sync-text">
                <?php if ($sync_in_progress): ?>
                    Syncing now...
                <?php elseif ($last_sync_time): ?>
                    Synced <?php echo timeAgo($last_sync_time); ?> | Next: <?php echo $next_sync; ?>
                <?php else: ?>
                    Never synced
                <?php endif; ?>
                </span>
            </span>
            <?php endif; ?>
            <span class="text-muted">Welcome back, <?php echo sanitize($_SESSION['name']); ?>!</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-globe2"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Domains</div>
                <div class="stat-value"><?php echo number_format($total_domains); ?></div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-envelope-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Emails</div>
                <div class="stat-value"><?php echo number_format($total_emails); ?></div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Emails</div>
                <div class="stat-value"><?php echo number_format($active_emails); ?></div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-pause-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Suspended</div>
                <div class="stat-value"><?php echo number_format($suspended_emails); ?></div>
            </div>
        </div>
        <div class="stat-card secondary">
            <div class="stat-icon">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Aliases</div>
                <div class="stat-value"><?php echo number_format($total_aliases); ?></div>
            </div>
        </div>
        <?php if (isSuperAdmin()): ?>
        <div class="stat-card default">
            <div class="stat-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($total_users); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isSuperAdmin() && $failed_ops_count > 0): ?>
    <div class="alert alert-danger d-flex align-items-center justify-content-between mb-4" role="alert">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong><?php echo $failed_ops_count; ?> Failed Operation<?php echo $failed_ops_count > 1 ? 's' : ''; ?> Pending</strong>
            - Operations where Google succeeded but database failed. Requires attention.
        </div>
        <a href="failed_operations.php" class="btn btn-danger btn-sm">
            <i class="bi bi-arrow-right me-1"></i> View & Resolve
        </a>
    </div>
    <?php endif; ?>

    <?php if (isSuperAdmin() && $removed_domains_count > 0): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4" role="alert">
        <div>
            <i class="bi bi-globe me-2"></i>
            <strong><?php echo $removed_domains_count; ?> Domain<?php echo $removed_domains_count > 1 ? 's' : ''; ?> Removed from Google Workspace</strong>
            - These domains are no longer available in Google and have been soft-deleted.
        </div>
        <a href="domains.php?google_status=removed" class="btn btn-warning btn-sm">
            <i class="bi bi-arrow-right me-1"></i> View Removed
        </a>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Domains Overview -->
        <div class="col-lg-6 mb-4">
            <div class="content-card dashboard-card">
                <div class="card-header">
                    <h5><i class="bi bi-globe me-2"></i>Domains Overview</h5>
                    <a href="domains.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($domains_overview->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>Emails</th>
                                    <th>Aliases</th>
                                    <th>Last Sync</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($domain = $domains_overview->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($domain['domain_name']); ?></strong></td>
                                    <td>
                                        <?php if ($domain['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $domain['email_count']; ?></td>
                                    <td><?php echo $domain['alias_count']; ?></td>
                                    <td><small class="text-muted"><?php echo timeAgo($domain['last_google_sync']); ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-4">No domains found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="content-card dashboard-card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                    <a href="activity_logs.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($recent_activity->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>User</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo sanitize($activity['action']); ?></td>
                                    <td><small class="text-muted"><?php echo sanitize($activity['user_name'] ?? 'System'); ?></small></td>
                                    <td><small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-4">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="content-card">
        <div class="card-header">
            <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="emails.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-envelope-plus d-block mb-2" style="font-size: 1.5rem;"></i>
                        Manage Emails
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="aliases.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-arrow-left-right d-block mb-2" style="font-size: 1.5rem;"></i>
                        Manage Aliases
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="domains.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-globe d-block mb-2" style="font-size: 1.5rem;"></i>
                        View Domains
                    </a>
                </div>
                <?php if (isSuperAdmin()): ?>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="users.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-people d-block mb-2" style="font-size: 1.5rem;"></i>
                        Manage Users
                    </a>
                </div>
                <?php else: ?>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="settings.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-gear d-block mb-2" style="font-size: 1.5rem;"></i>
                        Settings
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (isSuperAdmin()): ?>
<script>
// Auto-refresh sync status every 10 seconds
(function() {
    const badge = document.getElementById('sync-status-badge');
    const icon = document.getElementById('sync-icon');
    const text = document.getElementById('sync-text');

    if (!badge || !icon || !text) return;

    function updateSyncStatus() {
        fetch('sync_status.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Update badge color
                badge.classList.remove('bg-warning', 'bg-success');
                badge.classList.add(data.syncing ? 'bg-warning' : 'bg-success');

                // Update icon
                icon.classList.remove('bi-arrow-repeat', 'bi-cloud-check');
                icon.classList.add(data.syncing ? 'bi-arrow-repeat' : 'bi-cloud-check');

                // Update text
                if (data.syncing) {
                    text.textContent = 'Syncing now...';
                } else if (data.last_sync) {
                    text.textContent = 'Synced ' + data.last_sync_ago + ' | Next: ' + data.next_sync;
                } else {
                    text.textContent = 'Never synced';
                }

                // Update tooltip
                badge.title = data.last_sync_formatted ? 'Last: ' + data.last_sync_formatted : 'Never synced';
            })
            .catch(() => {});
    }

    // Update every 10 seconds
    setInterval(updateSyncStatus, 10000);
})();
</script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
