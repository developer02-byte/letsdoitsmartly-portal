<?php
/**
 * Users Management
 * Unified Email Management Portal
 * Super Admin Only
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';

requireLogin();

// Only super admin can access
if (!isSuperAdmin()) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Users';

// Get all users with their domain info
$users = $conn->query("
    SELECT u.*,
           do.id as owner_id, do.company_name, do.phone, do.admin_id, do.owner_email,
           COUNT(DISTINCT d.id) as domain_count
    FROM users u
    LEFT JOIN domain_owners do ON u.id = do.user_id
    LEFT JOIN domains d ON do.id = d.domain_owner_id
    GROUP BY u.id
    ORDER BY u.role DESC, u.name ASC
");

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-people"></i> Users</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-1"></i> Add User
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php
        switch ($_GET['success']) {
            case 'added': echo 'User created successfully.'; break;
            case 'updated': echo 'User updated successfully.'; break;
            case 'deleted': echo 'User deleted successfully.'; break;
            case 'password_reset': echo 'Password has been reset.'; break;
            default: echo 'Operation completed successfully.';
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

    <!-- Users Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if ($users && $users->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Company</th>
                            <th>Domains</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($user['name']); ?></strong>
                                <br><small class="text-muted"><?php echo sanitize($user['email']); ?></small>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'super_admin'): ?>
                                <span class="badge bg-primary">Super Admin</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Client Admin</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['company_name']): ?>
                                <?php echo sanitize($user['company_name']); ?>
                                <?php if ($user['admin_id']): ?>
                                <br><small class="text-muted">ID: <?php echo sanitize($user['admin_id']); ?></small>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'client_admin'): ?>
                                <?php echo number_format($user['domain_count']); ?>
                                <?php else: ?>
                                <span class="text-muted">All</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary edit-user"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo sanitize($user['name']); ?>"
                                            data-email="<?php echo sanitize($user['email']); ?>"
                                            data-role="<?php echo $user['role']; ?>"
                                            data-status="<?php echo $user['status']; ?>"
                                            data-company="<?php echo sanitize($user['company_name'] ?? ''); ?>"
                                            data-phone="<?php echo sanitize($user['phone'] ?? ''); ?>"
                                            data-adminid="<?php echo sanitize($user['admin_id'] ?? ''); ?>"
                                            data-owneremail="<?php echo sanitize($user['owner_email'] ?? ''); ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning reset-password"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo sanitize($user['name']); ?>"
                                            title="Reset Password">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <?php if ($user['id'] != getCurrentUserId()): ?>
                                    <button type="button" class="btn btn-outline-danger delete-user"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo sanitize($user['name']); ?>"
                                            data-domains="<?php echo $user['domain_count']; ?>"
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">No Users Found</h5>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="user_handler.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="add_role" class="form-select" required>
                                <option value="client_admin">Client Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Client Admin Fields -->
                    <div id="client_fields">
                        <hr>
                        <h6 class="mb-3">Client Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Admin ID <span class="text-danger">*</span></label>
                                <input type="text" name="admin_id" id="add_admin_id" class="form-control"
                                       placeholder="unique-client-id">
                                <small class="text-muted">Unique identifier for this client</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">OTP Notification Email</label>
                                <input type="email" name="owner_email" class="form-control"
                                       placeholder="owner@company.com">
                                <small class="text-muted">Email for OTP verification (defaults to login email)</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="user_handler.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" id="edit_role_display" class="form-control" readonly disabled>
                            <small class="text-muted">Role cannot be changed after creation</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Client Admin Fields -->
                    <div id="edit_client_fields">
                        <hr>
                        <h6 class="mb-3">Client Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" id="edit_company" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">OTP Notification Email</label>
                            <input type="email" name="owner_email" id="edit_owner_email" class="form-control"
                                   placeholder="owner@company.com">
                            <small class="text-muted">Email for OTP verification (defaults to login email if empty)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="user_handler.php" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for <strong id="reset_user_name"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key me-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="user_handler.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_user_name"></strong>?</p>
                    <div class="alert alert-warning" id="delete_user_warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This user owns <strong id="delete_domain_count"></strong> domain(s).
                        You must reassign or delete these domains first.
                    </div>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_user_btn">
                        <i class="bi bi-trash me-1"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle client fields based on role
    const addRole = document.getElementById('add_role');
    const clientFields = document.getElementById('client_fields');
    const addAdminId = document.getElementById('add_admin_id');

    addRole.addEventListener('change', function() {
        if (this.value === 'client_admin') {
            clientFields.style.display = 'block';
            addAdminId.required = true;
        } else {
            clientFields.style.display = 'none';
            addAdminId.required = false;
        }
    });

    // Edit User
    document.querySelectorAll('.edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_role_display').value = this.dataset.role === 'super_admin' ? 'Super Admin' : 'Client Admin';
            document.getElementById('edit_status').value = this.dataset.status;
            document.getElementById('edit_company').value = this.dataset.company;
            document.getElementById('edit_phone').value = this.dataset.phone;
            document.getElementById('edit_owner_email').value = this.dataset.owneremail;

            // Show/hide client fields
            const editClientFields = document.getElementById('edit_client_fields');
            if (this.dataset.role === 'client_admin') {
                editClientFields.style.display = 'block';
            } else {
                editClientFields.style.display = 'none';
            }

            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
    });

    // Reset Password
    document.querySelectorAll('.reset-password').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('reset_user_id').value = this.dataset.id;
            document.getElementById('reset_user_name').textContent = this.dataset.name;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        });
    });

    // Delete User
    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_user_id').value = this.dataset.id;
            document.getElementById('delete_user_name').textContent = this.dataset.name;

            const domainCount = parseInt(this.dataset.domains);
            const warning = document.getElementById('delete_user_warning');
            const deleteBtn = document.getElementById('delete_user_btn');

            if (domainCount > 0) {
                warning.style.display = 'block';
                document.getElementById('delete_domain_count').textContent = domainCount;
                deleteBtn.disabled = true;
            } else {
                warning.style.display = 'none';
                deleteBtn.disabled = false;
            }

            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        });
    });
});
</script>

<?php include 'templates/footer.php'; ?>
