<?php
/**
 * Email Aliases Management
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

$page_title = 'Email Aliases';

// Get filter parameters
$filter_email = isset($_GET['email']) ? intval($_GET['email']) : 0;
$filter_domain = isset($_GET['domain']) ? intval($_GET['domain']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on role
$where_conditions = [];
$params = [];
$param_types = '';

// Role-based filtering
if (!isSuperAdmin()) {
    $allowed_ids = getAllowedDomainIds();
    if (empty($allowed_ids)) {
        $where_conditions[] = "1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $where_conditions[] = "e.domain_id IN ($placeholders)";
        foreach ($allowed_ids as $id) {
            $params[] = $id;
            $param_types .= 'i';
        }
    }
}

// Email filter
if ($filter_email > 0) {
    $where_conditions[] = "ea.email_account_id = ?";
    $params[] = $filter_email;
    $param_types .= 'i';
}

// Domain filter
if ($filter_domain > 0) {
    $where_conditions[] = "e.domain_id = ?";
    $params[] = $filter_domain;
    $param_types .= 'i';
}

// Search filter
if ($search) {
    $where_conditions[] = "(ea.alias_address LIKE ? OR e.email_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get aliases
$sql = "
    SELECT ea.*, e.email_address, e.first_name, e.last_name, d.domain_name, d.id as domain_id
    FROM email_aliases ea
    JOIN email_accounts e ON ea.email_account_id = e.id
    JOIN domains d ON e.domain_id = d.id
    $where_clause
    ORDER BY ea.alias_address ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $aliases = $stmt->get_result();
} else {
    $aliases = $conn->query($sql);
}

// Get domains for filter
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

// Get email accounts for add modal
if (isSuperAdmin()) {
    $email_accounts = $conn->query("
        SELECT e.id, e.email_address, d.domain_name
        FROM email_accounts e
        JOIN domains d ON e.domain_id = d.id
        WHERE e.status = 'active'
        ORDER BY d.domain_name, e.email_address
    ");
} else {
    $allowed_ids = getAllowedDomainIds();
    if (!empty($allowed_ids)) {
        $ids = implode(',', array_map('intval', $allowed_ids));
        $email_accounts = $conn->query("
            SELECT e.id, e.email_address, d.domain_name
            FROM email_accounts e
            JOIN domains d ON e.domain_id = d.id
            WHERE e.domain_id IN ($ids) AND e.status = 'active'
            ORDER BY d.domain_name, e.email_address
        ");
    } else {
        $email_accounts = false;
    }
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-arrow-left-right"></i> Email Aliases</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAliasModal">
            <i class="bi bi-plus-lg me-1"></i> Add Alias
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php
        switch ($_GET['success']) {
            case 'added': echo 'Alias created successfully.'; break;
            case 'deleted': echo 'Alias deleted successfully.'; break;
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
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search alias or email..."
                           value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="aliases.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Aliases Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if ($aliases && $aliases->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Alias Address</th>
                            <th>Forwards To</th>
                            <th>Domain</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($alias = $aliases->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($alias['alias_address']); ?></strong>
                            </td>
                            <td>
                                <a href="emails.php?search=<?php echo urlencode($alias['email_address']); ?>" class="text-decoration-none">
                                    <?php echo sanitize($alias['email_address']); ?>
                                </a>
                                <?php
                                $owner_name = trim(($alias['first_name'] ?? '') . ' ' . ($alias['last_name'] ?? ''));
                                if ($owner_name): ?>
                                <br><small class="text-muted"><?php echo sanitize($owner_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="aliases.php?domain=<?php echo $alias['domain_id']; ?>" class="text-decoration-none">
                                    <?php echo sanitize($alias['domain_name']); ?>
                                </a>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($alias['created_at'])); ?></small>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-alias"
                                        data-id="<?php echo $alias['id']; ?>"
                                        data-alias="<?php echo sanitize($alias['alias_address']); ?>"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-arrow-left-right text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">No Aliases Found</h5>
                <p class="text-muted">
                    <?php if ($search || $filter_domain || $filter_email): ?>
                    No aliases match your filters.
                    <a href="aliases.php">Clear filters</a>
                    <?php else: ?>
                    Get started by adding your first email alias.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$filter_domain && !$filter_email): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAliasModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Alias
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Alias Modal -->
<div class="modal fade" id="addAliasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="alias_handler.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add Email Alias</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Forward To (Email Account) <span class="text-danger">*</span></label>
                        <select name="email_account_id" id="add_email_account" class="form-select" required>
                            <option value="">Select Email Account</option>
                            <?php if ($email_accounts): while ($e = $email_accounts->fetch_assoc()): ?>
                            <option value="<?php echo $e['id']; ?>" data-domain="<?php echo sanitize($e['domain_name']); ?>">
                                <?php echo sanitize($e['email_address']); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                        <small class="text-muted">Select the email account this alias will forward to</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alias Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="alias_username" id="add_alias_username" class="form-control"
                                   required pattern="^[a-zA-Z0-9._-]+$" placeholder="alias">
                            <span class="input-group-text" id="add_alias_domain">@domain.com</span>
                        </div>
                        <small class="text-muted">The alias will be on the same domain as the email account</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Add Alias
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Alias Modal -->
<div class="modal fade" id="deleteAliasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="alias_handler.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="alias_id" id="delete_alias_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Alias</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the alias <strong id="delete_alias_address"></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Alias
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update domain suffix when email account is selected
    const emailAccount = document.getElementById('add_email_account');
    const aliasDomain = document.getElementById('add_alias_domain');

    emailAccount.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            aliasDomain.textContent = '@' + selected.dataset.domain;
        } else {
            aliasDomain.textContent = '@domain.com';
        }
    });

    // Delete Alias
    document.querySelectorAll('.delete-alias').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_alias_id').value = this.dataset.id;
            document.getElementById('delete_alias_address').textContent = this.dataset.alias;
            new bootstrap.Modal(document.getElementById('deleteAliasModal')).show();
        });
    });
});
</script>

<?php include 'templates/footer.php'; ?>
