<?php
/**
 * Settings - Security Page
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

$page_title = 'Security Settings';

// Get current user data
$user_id = getCurrentUserId();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

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
                <a class="nav-link active" href="settings_security.php">
                    <i class="bi bi-shield-lock me-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings_account.php">
                    <i class="bi bi-gear me-2"></i>Account
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="content-card">
    <div class="card-body">
        <!-- Flash Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php
            switch ($_GET['success']) {
                case 'password_changed': echo 'Password changed successfully.'; break;
                default: echo 'Security settings updated successfully.';
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

        <div class="row">
            <!-- Change Password -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-key me-2"></i>Change Password</h5>
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

            <!-- Security Information -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-shield-check me-2"></i>Security Information</h5>

                <div class="alert alert-info">
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
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    function validatePassword() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }

    newPassword.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
});
</script>

<?php include 'templates/footer.php'; ?>
