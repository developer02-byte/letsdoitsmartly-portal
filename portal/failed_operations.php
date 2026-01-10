<?php
/**
 * Failed Operations Management
 * Admin page to view and resolve failed operations
 * Super Admin only
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';
require_once 'includes/error_recovery.php';

requireLogin();

// Super admin only
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Failed Operations';

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// Valid statuses
$valid_statuses = ['pending', 'auto_resolved', 'manual_resolved', 'failed_permanent', 'all'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'pending';
}

// Get failed operations with filters
$filters = ['limit' => 100];
if ($filter_status !== 'all') {
    $filters['status'] = $filter_status;
}
if ($filter_type) {
    $filters['operation_type'] = $filter_type;
}

$failed_operations = getFailedOperations($conn, $filters);

// Get counts for tabs
$pending_count = getPendingFailedOperationsCount($conn);
$result = $conn->query("SELECT status, COUNT(*) as cnt FROM failed_operations GROUP BY status");
$status_counts = [];
while ($row = $result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['cnt'];
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-exclamation-triangle"></i> Failed Operations</h2>
        <div>
            <button class="btn btn-warning me-2" id="autoResolveAllBtn" <?php echo $pending_count == 0 ? 'disabled' : ''; ?>>
                <i class="bi bi-magic me-1"></i> Auto-Resolve All Pending
            </button>
            <button class="btn btn-outline-secondary" id="runConsistencyCheckBtn">
                <i class="bi bi-search me-1"></i> Run Consistency Check
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo sanitize($_GET['success']); ?>
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

    <!-- Info Card -->
    <?php if ($pending_count > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-info-circle me-2"></i>
        <strong><?php echo $pending_count; ?> pending operation(s)</strong> need attention.
        These are operations where Google API succeeded but the local database failed.
        The system will attempt auto-recovery during each sync cycle.
    </div>
    <?php endif; ?>

    <!-- Status Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                Pending <span class="badge bg-warning text-dark"><?php echo $status_counts['pending'] ?? 0; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'auto_resolved' ? 'active' : ''; ?>" href="?status=auto_resolved">
                Auto-Resolved <span class="badge bg-success"><?php echo $status_counts['auto_resolved'] ?? 0; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'manual_resolved' ? 'active' : ''; ?>" href="?status=manual_resolved">
                Manual <span class="badge bg-info"><?php echo $status_counts['manual_resolved'] ?? 0; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'failed_permanent' ? 'active' : ''; ?>" href="?status=failed_permanent">
                Failed <span class="badge bg-danger"><?php echo $status_counts['failed_permanent'] ?? 0; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'all' ? 'active' : ''; ?>" href="?status=all">
                All
            </a>
        </li>
    </ul>

    <!-- Operations Table -->
    <div class="content-card">
        <div class="card-body">
            <?php if (!empty($failed_operations)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Resource</th>
                            <th>Error</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_operations as $op): ?>
                        <tr>
                            <td><?php echo $op['id']; ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo str_replace('_', ' ', ucfirst($op['operation_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo sanitize($op['resource_identifier']); ?></strong>
                                <?php if ($op['related_email']): ?>
                                <br><small class="text-muted">for <?php echo sanitize($op['related_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;"
                                      title="<?php echo sanitize($op['error_message']); ?>">
                                    <?php echo sanitize(substr($op['error_message'], 0, 50)); ?>
                                    <?php if (strlen($op['error_message']) > 50) echo '...'; ?>
                                </span>
                                <br><small class="text-muted"><?php echo sanitize($op['error_code']); ?></small>
                            </td>
                            <td>
                                <?php
                                $status_badges = [
                                    'pending' => 'warning text-dark',
                                    'auto_resolved' => 'success',
                                    'manual_resolved' => 'info',
                                    'failed_permanent' => 'danger'
                                ];
                                $badge = $status_badges[$op['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $op['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $op['resolution_attempts']; ?></td>
                            <td>
                                <small><?php echo date('M j, H:i', strtotime($op['created_at'])); ?></small>
                                <?php if ($op['initiated_by_name']): ?>
                                <br><small class="text-muted">by <?php echo sanitize($op['initiated_by_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($op['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-success retry-btn"
                                        data-id="<?php echo $op['id']; ?>" title="Retry Auto-Resolve">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info manual-resolve-btn"
                                        data-id="<?php echo $op['id']; ?>"
                                        data-resource="<?php echo sanitize($op['resource_identifier']); ?>"
                                        title="Mark Manually Resolved">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger mark-failed-btn"
                                        data-id="<?php echo $op['id']; ?>"
                                        data-resource="<?php echo sanitize($op['resource_identifier']); ?>"
                                        title="Mark as Permanently Failed">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary view-details-btn"
                                        data-id="<?php echo $op['id']; ?>"
                                        data-notes="<?php echo sanitize($op['resolution_notes'] ?? ''); ?>"
                                        title="View Details">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle display-4 text-success"></i>
                <p class="mt-3 text-muted">
                    <?php if ($filter_status === 'pending'): ?>
                        No pending operations. All systems healthy!
                    <?php else: ?>
                        No operations found with this status.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Manual Resolve Modal -->
<div class="modal fade" id="manualResolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Manually Resolved</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are marking <strong id="manualResolveResource"></strong> as manually resolved.</p>
                <div class="mb-3">
                    <label class="form-label">Resolution Notes</label>
                    <textarea id="manualResolveNotes" class="form-control" rows="3"
                              placeholder="Describe how you resolved this issue..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmManualResolve">
                    <i class="bi bi-check-lg me-1"></i> Mark Resolved
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Failed Modal -->
<div class="modal fade" id="markFailedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Permanently Failed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This will stop retry attempts for <strong id="markFailedResource"></strong>.
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for marking as failed</label>
                    <textarea id="markFailedNotes" class="form-control" rows="3"
                              placeholder="Explain why this cannot be resolved..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmMarkFailed">
                    <i class="bi bi-x-lg me-1"></i> Mark Failed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resolution Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="detailsContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo generateCSRFToken(); ?>';
    let currentOpId = null;

    // Retry auto-resolve
    document.querySelectorAll('.retry-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('failed_ops_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=auto_resolve&op_id=${id}&csrf_token=${csrfToken}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + data.error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
                }
            });
        });
    });

    // Manual resolve
    document.querySelectorAll('.manual-resolve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentOpId = this.dataset.id;
            document.getElementById('manualResolveResource').textContent = this.dataset.resource;
            document.getElementById('manualResolveNotes').value = '';
            new bootstrap.Modal(document.getElementById('manualResolveModal')).show();
        });
    });

    document.getElementById('confirmManualResolve').addEventListener('click', function() {
        const notes = document.getElementById('manualResolveNotes').value;
        this.disabled = true;

        fetch('failed_ops_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=manual_resolve&op_id=${currentOpId}&notes=${encodeURIComponent(notes)}&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed: ' + data.error);
                this.disabled = false;
            }
        });
    });

    // Mark failed
    document.querySelectorAll('.mark-failed-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentOpId = this.dataset.id;
            document.getElementById('markFailedResource').textContent = this.dataset.resource;
            document.getElementById('markFailedNotes').value = '';
            new bootstrap.Modal(document.getElementById('markFailedModal')).show();
        });
    });

    document.getElementById('confirmMarkFailed').addEventListener('click', function() {
        const notes = document.getElementById('markFailedNotes').value;
        this.disabled = true;

        fetch('failed_ops_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=mark_failed&op_id=${currentOpId}&notes=${encodeURIComponent(notes)}&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed: ' + data.error);
                this.disabled = false;
            }
        });
    });

    // View details
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('detailsContent').textContent = this.dataset.notes || 'No resolution notes available.';
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        });
    });

    // Auto-resolve all
    document.getElementById('autoResolveAllBtn').addEventListener('click', function() {
        if (!confirm('This will attempt to auto-resolve all pending operations. Continue?')) return;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        fetch('failed_ops_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=auto_resolve_all&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Processed ${data.processed} operations: ${data.resolved} resolved, ${data.failed} failed`);
                location.reload();
            } else {
                alert('Failed: ' + data.error);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-magic me-1"></i> Auto-Resolve All Pending';
            }
        });
    });

    // Consistency check
    document.getElementById('runConsistencyCheckBtn').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';

        fetch('failed_ops_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=consistency_check&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let msg = 'Consistency Check Results:\n\n';
                for (const domain of data.results) {
                    msg += `${domain.domain}: ${domain.total_issues} issues found\n`;
                }
                if (data.results.length === 0) {
                    msg = 'All domains are consistent with Google Workspace!';
                }
                alert(msg);
            } else {
                alert('Failed: ' + data.error);
            }
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search me-1"></i> Run Consistency Check';
        });
    });
});
</script>

<?php include 'templates/footer.php'; ?>
