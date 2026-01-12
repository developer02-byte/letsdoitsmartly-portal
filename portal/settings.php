<?php
/**
 * Unified Settings Page
 * 3 Tabs: Profile, Security, Account
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';

requireLogin();

$page_title = 'Settings';
$current_tab = $_GET['tab'] ?? 'profile';

// Validate tab
$allowed_tabs = ['profile', 'security', 'account'];
if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'profile';
}

// Get current user data
$user_id = getCurrentUserId();
$stmt = $conn->prepare("
    SELECT u.*, do.company_name, do.phone, do.admin_id
    FROM users u
    LEFT JOIN domain_owners do ON u.id = do.user_id
    WHERE u.id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get recent login activity for Account tab
$recent_logins = [];
if ($current_tab === 'account') {
    $login_stmt = $conn->prepare("
        SELECT auth_type, ip_address, user_agent, created_at
        FROM auth_logs
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $login_stmt->bind_param('i', $user_id);
    $login_stmt->execute();
    $logins_result = $login_stmt->get_result();
    while ($login = $logins_result->fetch_assoc()) {
        $recent_logins[] = $login;
    }
}

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

.tab-content-wrapper {
    display: none;
}

.tab-content-wrapper.active {
    display: block;
}

.settings-section-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-section-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
}

.settings-section-header i {
    font-size: 1.3rem;
}

.form-control:disabled,
.form-control[readonly] {
    background-color: #f8f9fa;
    border-color: #e9ecef;
    color: #495057;
    cursor: not-allowed;
    opacity: 1;
}

.form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 8px;
}

.settings-info-box {
    background: #e7f3ff;
    border-left: 4px solid var(--primary);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.settings-info-box h6 {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 10px;
}

.settings-info-box ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.settings-info-box li {
    margin-bottom: 5px;
    color: #495057;
}

/* Reduce padding for settings page */
.settings-tabs .card-body {
    padding: 20px 25px;
}
</style>

    <!-- Tabs Navigation Card -->
    <div class="content-card mb-0">
        <div class="card-body pb-0">
            <ul class="nav nav-tabs settings-tabs border-0 mb-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_tab === 'profile' ? 'active' : ''; ?>"
                       href="?tab=profile">
                        <i class="bi bi-person me-2"></i>Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_tab === 'security' ? 'active' : ''; ?>"
                       href="?tab=security">
                        <i class="bi bi-shield-lock me-2"></i>Security
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_tab === 'account' ? 'active' : ''; ?>"
                       href="?tab=account">
                        <i class="bi bi-gear me-2"></i>Account
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content Card -->
    <div class="content-card">
        <div class="card-body">
            <!-- Flash Messages -->
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php
                switch ($_GET['success']) {
                    case 'profile_updated': echo 'Profile updated successfully.'; break;
                    case 'password_changed': echo 'Password changed successfully.'; break;
                    default: echo 'Settings saved successfully.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo sanitize($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Profile Tab -->
            <div class="tab-content-wrapper <?php echo $current_tab === 'profile' ? 'active' : ''; ?>" id="profileTab">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-person"></i>
                            <h5>Profile Information</h5>
                        </div>
                        <form action="settings_handler.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo sanitize($user['name']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo sanitize($user['email']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" readonly disabled
                                       value="<?php echo $user['role'] === 'super_admin' ? 'Super Admin' : 'Client Admin'; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Account Status</label>
                                <input type="text" class="form-control" readonly disabled
                                       value="<?php echo ucfirst($user['status']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" readonly disabled
                                       value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <?php if ($user['role'] === 'client_admin' && $user['company_name']): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-building"></i>
                            <h5>Company Information</h5>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" readonly disabled
                                   value="<?php echo sanitize($user['company_name']); ?>">
                        </div>

                        <?php if ($user['phone']): ?>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" readonly disabled
                                   value="<?php echo sanitize($user['phone']); ?>">
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Admin ID</label>
                            <input type="text" class="form-control" readonly disabled
                                   value="<?php echo sanitize($user['admin_id']); ?>">
                        </div>

                        <p class="text-muted mb-0">
                            <small><i class="bi bi-info-circle me-1"></i>Contact your administrator to update company information.</small>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content-wrapper <?php echo $current_tab === 'security' ? 'active' : ''; ?>" id="securityTab">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-key"></i>
                            <h5>Change Password</h5>
                        </div>
                        <form action="settings_handler.php" method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" id="new_password" class="form-control"
                                       required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                       required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-shield-check"></i>
                            <h5>Security Information</h5>
                        </div>

                        <div class="settings-info-box">
                            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Password Requirements</h6>
                            <ul class="mb-0">
                                <li>Minimum 8 characters long</li>
                                <li>Use a strong, unique password</li>
                                <li>Avoid common words or patterns</li>
                                <li>Change your password regularly</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Last Password Change</label>
                            <input type="text" class="form-control" readonly disabled
                                   value="<?php echo $user['last_password_change'] ? date('F j, Y g:i A', strtotime($user['last_password_change'])) : 'Never'; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Created</label>
                            <input type="text" class="form-control" readonly disabled
                                   value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Tab -->
            <div class="tab-content-wrapper <?php echo $current_tab === 'account' ? 'active' : ''; ?>" id="accountTab">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-clock-history"></i>
                            <h5>Session Information</h5>
                        </div>

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

                    <div class="col-lg-6 mb-4">
                        <div class="settings-section-header">
                            <i class="bi bi-person-badge"></i>
                            <h5>Account Information</h5>
                        </div>

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
                        <div class="settings-section-header">
                            <i class="bi bi-activity"></i>
                            <h5>Recent Login Activity</h5>
                        </div>
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
                                    <?php if (count($recent_logins) > 0): ?>
                                        <?php foreach ($recent_logins as $login): ?>
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
                                        <?php endforeach; ?>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    if (newPassword && confirmPassword) {
        function validatePassword() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        newPassword.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
});
</script>

<?php include 'templates/footer.php'; ?>
