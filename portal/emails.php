<?php
/**
 * Email Accounts Management
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

$page_title = 'Email Accounts';

// Get filter parameters
$filter_domain = isset($_GET['domain']) ? intval($_GET['domain']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on role
$where_conditions = [];
$params = [];
$param_types = '';

// Role-based filtering
if (isSuperAdmin()) {
    // Super admin can see all, but apply domain filter if specified
    if ($filter_domain > 0) {
        $where_conditions[] = "e.domain_id = ?";
        $params[] = $filter_domain;
        $param_types .= 'i';
    }
} else {
    // Client admin only sees their domains
    $allowed_ids = getAllowedDomainIds();
    if (empty($allowed_ids)) {
        $where_conditions[] = "1=0"; // No access
    } else {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $where_conditions[] = "e.domain_id IN ($placeholders)";
        foreach ($allowed_ids as $id) {
            $params[] = $id;
            $param_types .= 'i';
        }

        // If domain filter specified, verify access
        if ($filter_domain > 0 && in_array($filter_domain, $allowed_ids)) {
            $where_conditions[] = "e.domain_id = ?";
            $params[] = $filter_domain;
            $param_types .= 'i';
        }
    }
}

// Status filter
if ($filter_status && in_array($filter_status, ['active', 'suspended'])) {
    $where_conditions[] = "e.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// Search filter
if ($search) {
    $where_conditions[] = "(e.email_address LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for display
$count_sql = "
    SELECT COUNT(DISTINCT e.id) as total
    FROM email_accounts e
    JOIN domains d ON e.domain_id = d.id
    $where_clause
";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_emails = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_emails = $conn->query($count_sql)->fetch_assoc()['total'];
}

// Get emails
$sql = "
    SELECT e.*, d.domain_name,
           COUNT(ea.id) as alias_count
    FROM email_accounts e
    JOIN domains d ON e.domain_id = d.id
    LEFT JOIN email_aliases ea ON e.id = ea.email_account_id
    $where_clause
    GROUP BY e.id
    ORDER BY d.domain_name ASC, e.email_address ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $emails = $stmt->get_result();
} else {
    $emails = $conn->query($sql);
}

// Get domains for filter dropdown and add modal
if (isSuperAdmin()) {
    $domains = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active' ORDER BY domain_name");
} else {
    $allowed_ids = getAllowedDomainIds();
    if (!empty($allowed_ids)) {
        $ids = implode(',', array_map('intval', $allowed_ids));
        $domains = $conn->query("SELECT id, domain_name FROM domains WHERE id IN ($ids) AND status = 'active' ORDER BY domain_name");
    } else {
        $domains = false;
    }
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-envelope"></i> Email Accounts</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmailModal">
            <i class="bi bi-plus-lg me-1"></i> Add Email
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php
        switch ($_GET['success']) {
            case 'added': echo 'Email account created successfully.'; break;
            case 'updated': echo 'Email account updated successfully.'; break;
            case 'deleted': echo 'Email account deleted successfully.'; break;
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

    <!-- Filters -->
    <div class="content-card mb-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Domain</label>
                    <select name="domain" class="form-select">
                        <option value="">All Domains</option>
                        <?php if ($domains): $domains->data_seek(0); while ($d = $domains->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $filter_domain == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($d['domain_name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by email or name..."
                           value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="emails.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Emails Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if ($emails && $emails->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Aliases</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($email = $emails->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($email['email_address']); ?></strong>
                            </td>
                            <td>
                                <?php
                                $full_name = trim(($email['first_name'] ?? '') . ' ' . ($email['last_name'] ?? ''));
                                echo $full_name ? sanitize($full_name) : '<span class="text-muted">-</span>';
                                ?>
                            </td>
                            <td>
                                <a href="emails.php?domain=<?php echo $email['domain_id']; ?>" class="text-decoration-none">
                                    <?php echo sanitize($email['domain_name']); ?>
                                </a>
                            </td>
                            <td>
                                <a href="aliases.php?email=<?php echo $email['id']; ?>" class="text-decoration-none">
                                    <?php echo number_format($email['alias_count']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($email['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($email['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="aliases.php?email=<?php echo $email['id']; ?>"
                                       class="btn btn-info text-white" title="Manage Aliases">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                    <button type="button" class="btn btn-warning text-white edit-email"
                                            data-id="<?php echo $email['id']; ?>"
                                            data-email="<?php echo sanitize($email['email_address']); ?>"
                                            data-domain="<?php echo $email['domain_id']; ?>"
                                            data-firstname="<?php echo sanitize($email['first_name'] ?? ''); ?>"
                                            data-lastname="<?php echo sanitize($email['last_name'] ?? ''); ?>"
                                            data-status="<?php echo $email['status']; ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger delete-email"
                                            data-id="<?php echo $email['id']; ?>"
                                            data-email="<?php echo sanitize($email['email_address']); ?>"
                                            data-aliases="<?php echo $email['alias_count']; ?>"
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-envelope text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">No Email Accounts Found</h5>
                <p class="text-muted">
                    <?php if ($search || $filter_domain || $filter_status): ?>
                    No emails match your filters.
                    <a href="emails.php">Clear filters</a>
                    <?php else: ?>
                    Get started by adding your first email account.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$filter_domain && !$filter_status): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmailModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Email
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Email Modal -->
<div class="modal fade" id="addEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="email_handler.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-envelope-plus me-2"></i>Add Email Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domain <span class="text-danger">*</span></label>
                        <select name="domain_id" id="add_domain" class="form-select" required>
                            <option value="">Select Domain</option>
                            <?php if ($domains): $domains->data_seek(0); while ($d = $domains->fetch_assoc()): ?>
                            <option value="<?php echo $d['id']; ?>" data-domain="<?php echo sanitize($d['domain_name']); ?>">
                                <?php echo sanitize($d['domain_name']); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="username" id="add_username" class="form-control"
                                   required pattern="^[a-zA-Z0-9._-]+$" placeholder="username">
                            <span class="input-group-text" id="add_domain_suffix">@domain.com</span>
                        </div>
                        <small class="text-muted">Only letters, numbers, dots, hyphens, and underscores</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_password" class="form-control"
                                   required minlength="8" placeholder="Enter password">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleAddPassword()">
                                <i class="bi bi-eye" id="add_password_icon"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="generateAddPassword()">
                                <i class="bi bi-shuffle"></i>
                            </button>
                        </div>
                        <div class="password-requirements small mt-2" id="add_password_requirements">
                            <div class="req" data-rule="length"><i class="bi bi-circle"></i> At least 8 characters</div>
                            <div class="req" data-rule="complexity"><i class="bi bi-circle"></i> 3 of: uppercase, lowercase, number, special</div>
                            <div class="req" data-rule="no-email"><i class="bi bi-circle"></i> Cannot contain username</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Add Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Email Modal -->
<div class="modal fade" id="editEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="email_handler.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="email_id" id="edit_email_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Email Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="text" id="edit_email_address" class="form-control" readonly disabled>
                        <small class="text-muted">Email address cannot be changed</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
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

<!-- Delete Email Modal -->
<div class="modal fade" id="deleteEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="email_handler.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="email_id" id="delete_email_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Email</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_email_address"></strong>?</p>
                    <div class="alert alert-warning" id="delete_alias_warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This email has <strong id="delete_alias_count"></strong> alias(es) that will also be deleted.
                    </div>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password toggle for Add Email modal
function toggleAddPassword() {
    const field = document.getElementById('add_password');
    const icon = document.getElementById('add_password_icon');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Generate random password that always meets all requirements
function generateAddPassword() {
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    const special = '!@#$%^&*';
    const allChars = lowercase + uppercase + numbers + special;

    // Guarantee at least one from each category (ensures 4 of 4 complexity)
    let password = [
        lowercase.charAt(Math.floor(Math.random() * lowercase.length)),
        uppercase.charAt(Math.floor(Math.random() * uppercase.length)),
        numbers.charAt(Math.floor(Math.random() * numbers.length)),
        special.charAt(Math.floor(Math.random() * special.length))
    ];

    // Fill remaining 8 characters randomly (total 12)
    for (let i = 0; i < 8; i++) {
        password.push(allChars.charAt(Math.floor(Math.random() * allChars.length)));
    }

    // Shuffle the array so guaranteed chars aren't always at the start
    for (let i = password.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [password[i], password[j]] = [password[j], password[i]];
    }

    const field = document.getElementById('add_password');
    field.value = password.join('');
    field.type = 'text';
    document.getElementById('add_password_icon').classList.replace('bi-eye', 'bi-eye-slash');
}

document.addEventListener('DOMContentLoaded', function() {
    // Update domain suffix when domain is selected
    const addDomain = document.getElementById('add_domain');
    const domainSuffix = document.getElementById('add_domain_suffix');

    addDomain.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            domainSuffix.textContent = '@' + selected.dataset.domain;
        } else {
            domainSuffix.textContent = '@domain.com';
        }
    });

    // Edit Email
    document.querySelectorAll('.edit-email').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_email_id').value = this.dataset.id;
            document.getElementById('edit_email_address').value = this.dataset.email;
            document.getElementById('edit_first_name').value = this.dataset.firstname;
            document.getElementById('edit_last_name').value = this.dataset.lastname;
            document.getElementById('edit_status').value = this.dataset.status;
            new bootstrap.Modal(document.getElementById('editEmailModal')).show();
        });
    });

    // Delete Email
    document.querySelectorAll('.delete-email').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_email_id').value = this.dataset.id;
            document.getElementById('delete_email_address').textContent = this.dataset.email;

            const aliasCount = parseInt(this.dataset.aliases);
            if (aliasCount > 0) {
                document.getElementById('delete_alias_warning').style.display = 'block';
                document.getElementById('delete_alias_count').textContent = aliasCount;
            } else {
                document.getElementById('delete_alias_warning').style.display = 'none';
            }

            new bootstrap.Modal(document.getElementById('deleteEmailModal')).show();
        });
    });

    // Initialize password validation for Add Email form
    const addPasswordField = document.getElementById('add_password');
    const addUsernameField = document.getElementById('add_username');
    const addPasswordRequirements = document.getElementById('add_password_requirements');

    if (addPasswordField && addPasswordRequirements) {
        // Custom validate function that builds email from username + domain
        const validateAddPassword = () => {
            const password = addPasswordField.value;
            const username = addUsernameField ? addUsernameField.value : '';
            const domain = domainSuffix.textContent || '@domain.com';
            const email = username + domain;

            const result = validatePassword(password, email);
            updatePasswordRequirementsUI(addPasswordRequirements, result.checks);
            return result;
        };

        addPasswordField.addEventListener('input', debounce(validateAddPassword, 100));
        addPasswordField.addEventListener('focus', validateAddPassword);
        if (addUsernameField) {
            addUsernameField.addEventListener('input', debounce(validateAddPassword, 100));
        }

        // Also trigger validation when generated password is set
        const originalGenerateAddPassword = window.generateAddPassword;
        window.generateAddPassword = function() {
            originalGenerateAddPassword();
            validateAddPassword();
        };
    }
});
</script>

<?php include 'templates/footer.php'; ?>
