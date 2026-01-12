<?php
/**
 * Settings - Account Page
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

$page_title = 'Account Settings';

// Get current user data
$user_id = getCurrentUserId();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get recent login activity
$recent_logins = $conn->prepare("
    SELECT auth_type, ip_address, user_agent, created_at
    FROM auth_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recent_logins->bind_param('i', $user_id);
$recent_logins->execute();
$logins = $recent_logins->get_result();

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-gear"></i> Settings</h2>
    </div>

<style>
.settings-tabs .nav-link {
    border-radius: 8px 8px 0 0;
    color: #6c757d;
    font-weight: 500;
    transition: all 0.2s ease;
    padding: 12px 24px;
}

.settings-tabs .nav-link.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
}

.settings-tabs .nav-link:hover:not(.active) {
    background: #f8f9fa;
}
</style>

<!-- Tabs Navigation -->
<div class="content-card mb-0">
    <div class="card-body pb-0">
        <ul class="nav nav-tabs settings-tabs border-0 mb-0">
            <li class="nav-item">
                <a class="nav-link" href="settings_profile.php">
                    <i class="bi bi-person me-2"></i>Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings_security.php">
                    <i class="bi bi-shield-lock me-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="settings_account.php">
                    <i class="bi bi-gear me-2"></i>Account
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="content-card">
    <div class="card-body">
        <div class="row">
            <!-- Session Information -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-clock-history me-2"></i>Session Information</h5>

                <div class="mb-3">
                    <label class="form-label">Last Login</label>
                    <input type="text" class="form-control" readonly disabled
                           value="<?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Current session'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Current Session ID</label>
                    <input type="text" class="form-control" readonly disabled
                           value="<?php echo session_id() ? substr(session_id(), 0, 16) . '...' : 'N/A'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Session Expires In</label>
                    <input type="text" class="form-control" readonly disabled
                           value="<?php echo SESSION_LIFETIME / 60; ?> minutes">
                </div>

                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>

            <!-- Account Information -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-person-badge me-2"></i>Account Information</h5>

                <div class="mb-3">
                    <label class="form-label">User ID</label>
                    <input type="text" class="form-control" readonly disabled
                           value="#<?php echo $user['id']; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Account Status</label>
                    <div>
                        <?php if ($user['status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-danger"><?php echo ucfirst($user['status']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" readonly disabled
                           value="<?php echo $user['role'] === 'super_admin' ? 'Super Administrator' : 'Client Administrator'; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-control" readonly disabled
                           value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>">
                </div>
            </div>
        </div>

        <!-- Recent Login Activity -->
        <div class="row mt-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="bi bi-activity me-2"></i>Recent Login Activity</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Event Type</th>
                                <th>IP Address</th>
                                <th>Browser</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logins->num_rows > 0): ?>
                                <?php while ($login = $logins->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($login['auth_type'] === 'login'): ?>
                                        <span class="badge bg-success">Login</span>
                                        <?php elseif ($login['auth_type'] === 'logout'): ?>
                                        <span class="badge bg-secondary">Logout</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Failed Login</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo sanitize($login['ip_address']); ?></code>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo sanitize(substr($login['user_agent'], 0, 50)); ?>...</small>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($login['created_at'])); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No recent login activity</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="activity_logs_unified.php?tab=login" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i> View All Activity
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<?php include 'templates/footer.php'; ?>
