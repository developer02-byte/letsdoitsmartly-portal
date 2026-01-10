<?php
/**
 * Settings - Profile Page
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

$page_title = 'Profile Settings';

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
                <a class="nav-link active" href="settings_profile.php">
                    <i class="bi bi-person me-2"></i>Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings_security.php">
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
                case 'profile_updated': echo 'Profile updated successfully.'; break;
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

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-person me-2"></i>Profile Information</h5>
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
            <!-- Company Information (Client Admin Only) -->
            <div class="col-lg-6 mb-4">
                <h5 class="mb-3"><i class="bi bi-building me-2"></i>Company Information</h5>
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
</div>

</div>

<?php include 'templates/footer.php'; ?>
