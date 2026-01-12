<?php
/**
 * Domains Management
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

$page_title = 'Domains';

// Filter handling for Super Admin
$owner_filter = isset($_GET['owner']) ? $_GET['owner'] : 'all';
$google_status_filter = isset($_GET['google_status']) ? $_GET['google_status'] : 'active';

// Get domains based on role
if (isSuperAdmin()) {
    // Build query with optional filters
    $where_conditions = [];

    // Owner filter
    if ($owner_filter === 'unassigned') {
        $where_conditions[] = "d.domain_owner_id IS NULL";
    } elseif ($owner_filter !== 'all' && is_numeric($owner_filter)) {
        $where_conditions[] = "d.domain_owner_id = " . intval($owner_filter);
    }

    // Google status filter
    if ($google_status_filter === 'active') {
        $where_conditions[] = "(d.google_status = 'active' OR d.google_status IS NULL)";
    } elseif ($google_status_filter === 'removed') {
        $where_conditions[] = "d.google_status = 'removed'";
    }
    // 'all' shows everything

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    $domains = $conn->query("
        SELECT d.*, do.company_name, u.name as owner_name, u.email as owner_email,
               COUNT(DISTINCT e.id) as email_count,
               COUNT(DISTINCT ea.id) as alias_count
        FROM domains d
        LEFT JOIN domain_owners do ON d.domain_owner_id = do.id
        LEFT JOIN users u ON do.user_id = u.id
        LEFT JOIN email_accounts e ON d.id = e.domain_id
        LEFT JOIN email_aliases ea ON e.id = ea.email_account_id
        $where_clause
        GROUP BY d.id
        ORDER BY d.domain_name ASC
    ");

    // Get all domain owners for dropdown
    $domain_owners = $conn->query("
        SELECT do.id, do.company_name, do.admin_id, u.name, u.email
        FROM domain_owners do
        JOIN users u ON do.user_id = u.id
        ORDER BY do.company_name ASC
    ");

    // Count unassigned domains
    $unassigned_count = $conn->query("SELECT COUNT(*) as cnt FROM domains WHERE domain_owner_id IS NULL")->fetch_assoc()['cnt'];

    // Count removed domains
    $removed_count = $conn->query("SELECT COUNT(*) as cnt FROM domains WHERE google_status = 'removed'")->fetch_assoc()['cnt'];
} else {
    $allowed_ids = getAllowedDomainIds();
    if (empty($allowed_ids)) {
        $domains = false;
    } else {
        $ids = implode(',', array_map('intval', $allowed_ids));
        $domains = $conn->query("
            SELECT d.*, do.company_name,
                   COUNT(DISTINCT e.id) as email_count,
                   COUNT(DISTINCT ea.id) as alias_count
            FROM domains d
            LEFT JOIN domain_owners do ON d.domain_owner_id = do.id
            LEFT JOIN email_accounts e ON d.id = e.domain_id
            LEFT JOIN email_aliases ea ON e.id = ea.email_account_id
            WHERE d.id IN ($ids)
            GROUP BY d.id
            ORDER BY d.domain_name ASC
        ");
    }
    $domain_owners = null;
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-globe"></i> Domains</h2>
        <div class="d-flex gap-2 align-items-center">
            <?php if (isSuperAdmin()): ?>
            <!-- Google Status Filter -->
            <select class="form-select form-select-sm" style="width: auto;" onchange="updateFilters('google_status', this.value)">
                <option value="active" <?php echo $google_status_filter === 'active' ? 'selected' : ''; ?>>Active in Google</option>
                <option value="all" <?php echo $google_status_filter === 'all' ? 'selected' : ''; ?>>All Domains</option>
                <?php if ($removed_count > 0): ?>
                <option value="removed" <?php echo $google_status_filter === 'removed' ? 'selected' : ''; ?>>
                    Removed (<?php echo $removed_count; ?>)
                </option>
                <?php endif; ?>
            </select>
            <!-- Owner Filter -->
            <select class="form-select form-select-sm" style="width: auto;" onchange="updateFilters('owner', this.value)">
                <option value="all" <?php echo $owner_filter === 'all' ? 'selected' : ''; ?>>All Owners</option>
                <option value="unassigned" <?php echo $owner_filter === 'unassigned' ? 'selected' : ''; ?>>
                    Unassigned (<?php echo $unassigned_count; ?>)
                </option>
                <?php if ($domain_owners): $domain_owners->data_seek(0); while ($owner = $domain_owners->fetch_assoc()): ?>
                <option value="<?php echo $owner['id']; ?>" <?php echo $owner_filter == $owner['id'] ? 'selected' : ''; ?>>
                    <?php echo sanitize($owner['company_name'] ?: $owner['name']); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <a href="sync.php" class="btn btn-success btn-sm">
                <i class="bi bi-cloud-arrow-down me-1"></i> Sync
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                <i class="bi bi-plus-lg me-1"></i> Add Domain
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php
        switch ($_GET['success']) {
            case 'added': echo 'Domain added successfully.'; break;
            case 'updated': echo 'Domain updated successfully.'; break;
            case 'deleted': echo 'Domain deleted successfully.'; break;
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

    <!-- Domains Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if ($domains && $domains->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="domainsTable">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <?php if (isSuperAdmin()): ?>
                            <th>Owner</th>
                            <?php endif; ?>
                            <th>Emails</th>
                            <th>Aliases</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($domain = $domains->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($domain['domain_name']); ?></strong>
                            </td>
                            <?php if (isSuperAdmin()): ?>
                            <td>
                                <?php if ($domain['company_name']): ?>
                                    <span><?php echo sanitize($domain['company_name']); ?></span>
                                    <br><small class="text-muted"><?php echo sanitize($domain['owner_email'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="emails.php?domain=<?php echo $domain['id']; ?>" class="text-decoration-none">
                                    <?php echo number_format($domain['email_count']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($domain['alias_count']); ?></td>
                            <td>
                                <?php if (isset($domain['google_status']) && $domain['google_status'] === 'removed'): ?>
                                <span class="badge bg-danger">Removed from Google</span>
                                <br><small class="text-muted"><?php echo date('M j, Y', strtotime($domain['removed_at'])); ?></small>
                                <?php elseif ($domain['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo timeAgo($domain['last_google_sync']); ?>
                                </small>
                            </td>
                            <td>
                                <?php $is_removed = isset($domain['google_status']) && $domain['google_status'] === 'removed'; ?>
                                <div class="btn-group btn-group-sm">
                                    <?php if (!$is_removed): ?>
                                    <button type="button" class="btn btn-outline-success sync-domain"
                                            data-id="<?php echo $domain['id']; ?>"
                                            data-name="<?php echo sanitize($domain['domain_name']); ?>"
                                            title="Sync from Google">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="emails.php?domain=<?php echo $domain['id']; ?>"
                                       class="btn btn-outline-primary" title="View Emails">
                                        <i class="bi bi-envelope"></i>
                                    </a>
                                    <?php if (isSuperAdmin()): ?>
                                    <?php if (!$is_removed): ?>
                                    <button type="button" class="btn btn-outline-secondary edit-domain"
                                            data-id="<?php echo $domain['id']; ?>"
                                            data-name="<?php echo sanitize($domain['domain_name']); ?>"
                                            data-owner="<?php echo $domain['domain_owner_id']; ?>"
                                            data-status="<?php echo $domain['status']; ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-danger delete-domain"
                                            data-id="<?php echo $domain['id']; ?>"
                                            data-name="<?php echo sanitize($domain['domain_name']); ?>"
                                            data-emails="<?php echo $domain['email_count']; ?>"
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
                <i class="bi bi-globe text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">No Domains Found</h5>
                <p class="text-muted">
                    <?php if (isSuperAdmin()): ?>
                    Get started by adding your first domain.
                    <?php else: ?>
                    No domains have been assigned to your account.
                    <?php endif; ?>
                </p>
                <?php if (isSuperAdmin()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Domain
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isSuperAdmin()): ?>
<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="domain_handler.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-globe me-2"></i>Add Domain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" name="domain_name" class="form-control"
                               placeholder="example.com" required pattern="^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}$">
                        <small class="text-muted">Enter the domain without http:// or www</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain Owner</label>
                        <select name="domain_owner_id" class="form-select">
                            <option value="">Unassigned</option>
                            <?php if ($domain_owners): $domain_owners->data_seek(0); while ($owner = $domain_owners->fetch_assoc()): ?>
                            <option value="<?php echo $owner['id']; ?>">
                                <?php echo sanitize($owner['company_name'] ?: $owner['name']); ?>
                                (<?php echo sanitize($owner['email']); ?>)
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                        <small class="text-muted">
                            Leave as "Unassigned" to assign later. <a href="users.php">Manage clients</a>
                        </small>
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
                        <i class="bi bi-plus-lg me-1"></i> Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal fade" id="editDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="domain_handler.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="domain_id" id="edit_domain_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Domain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domain Name</label>
                        <input type="text" id="edit_domain_name" class="form-control" readonly disabled>
                        <small class="text-muted">Domain name cannot be changed</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Domain Owner</label>
                        <select name="domain_owner_id" id="edit_domain_owner" class="form-select">
                            <option value="">Unassigned</option>
                            <?php if ($domain_owners): $domain_owners->data_seek(0); while ($owner = $domain_owners->fetch_assoc()): ?>
                            <option value="<?php echo $owner['id']; ?>">
                                <?php echo sanitize($owner['company_name'] ?: $owner['name']); ?>
                                (<?php echo sanitize($owner['email']); ?>)
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_domain_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
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

<!-- Delete Domain Modal -->
<div class="modal fade" id="deleteDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="domain_handler.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="domain_id" id="delete_domain_id">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Domain</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_domain_name"></strong>?</p>
                    <div class="alert alert-warning" id="delete_warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This domain has <strong id="delete_email_count"></strong> email account(s).
                        All emails and aliases will be permanently deleted!
                    </div>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter update function
function updateFilters(filterName, filterValue) {
    const params = new URLSearchParams(window.location.search);
    params.set(filterName, filterValue);
    window.location.href = 'domains.php?' + params.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Sync Domain
    document.querySelectorAll('.sync-domain').forEach(btn => {
        btn.addEventListener('click', function() {
            const domainId = this.dataset.id;
            const domainName = this.dataset.name;
            const button = this;
            const icon = button.querySelector('i');

            // Disable button and show loading
            button.disabled = true;
            icon.classList.add('spin');

            // Make AJAX request
            const formData = new FormData();
            formData.append('action', 'sync_domain');
            formData.append('domain_id', domainId);
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

            fetch('sync_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    alert(`Sync completed for ${domainName}!\n\nFetched: ${stats.total_fetched}\nImported: ${stats.imported}\nUpdated: ${stats.updated}\nErrors: ${stats.errors}`);
                    // Reload page to show updated sync time
                    location.reload();
                } else {
                    alert('Sync failed: ' + data.error);
                }
            })
            .catch(error => {
                alert('Sync failed: ' + error.message);
            })
            .finally(() => {
                button.disabled = false;
                icon.classList.remove('spin');
            });
        });
    });

    // Edit Domain
    document.querySelectorAll('.edit-domain').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_domain_id').value = this.dataset.id;
            document.getElementById('edit_domain_name').value = this.dataset.name;
            document.getElementById('edit_domain_owner').value = this.dataset.owner;
            document.getElementById('edit_domain_status').value = this.dataset.status;
            new bootstrap.Modal(document.getElementById('editDomainModal')).show();
        });
    });

    // Delete Domain
    document.querySelectorAll('.delete-domain').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_domain_id').value = this.dataset.id;
            document.getElementById('delete_domain_name').textContent = this.dataset.name;
            const emailCount = parseInt(this.dataset.emails);
            if (emailCount > 0) {
                document.getElementById('delete_warning').style.display = 'block';
                document.getElementById('delete_email_count').textContent = emailCount;
            } else {
                document.getElementById('delete_warning').style.display = 'none';
            }
            new bootstrap.Modal(document.getElementById('deleteDomainModal')).show();
        });
    });
});
</script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
