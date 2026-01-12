<?php
/**
 * Google Workspace Sync Page
 * Simplified one-click sync for all domains, users, and aliases
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';

requireLogin();

// Only Super Admin can access sync
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Google Workspace Sync';

// Get current stats
$domain_count = $conn->query("SELECT COUNT(*) as cnt FROM domains WHERE status = 'active'")->fetch_assoc()['cnt'];
$user_count = $conn->query("SELECT COUNT(*) as cnt FROM email_accounts")->fetch_assoc()['cnt'];
$last_sync = $conn->query("SELECT MAX(last_google_sync) as last_sync FROM domains")->fetch_assoc()['last_sync'];

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-cloud-arrow-down"></i> Google Workspace Sync</h2>
        <a href="domains.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Domains
        </a>
    </div>

    <!-- Current Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="content-card">
                <div class="card-body text-center">
                    <i class="bi bi-globe text-primary" style="font-size: 2rem;"></i>
                    <h3 class="mt-2 mb-0"><?php echo $domain_count; ?></h3>
                    <p class="text-muted mb-0">Domains in Portal</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card">
                <div class="card-body text-center">
                    <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                    <h3 class="mt-2 mb-0"><?php echo $user_count; ?></h3>
                    <p class="text-muted mb-0">Email Accounts</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="content-card">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history text-info" style="font-size: 2rem;"></i>
                    <h3 class="mt-2 mb-0" style="font-size: 1.2rem;"><?php echo $last_sync ? timeAgo($last_sync) : 'Never'; ?></h3>
                    <p class="text-muted mb-0">Last Sync</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Card -->
    <div class="content-card">
        <div class="card-header">
            <h5><i class="bi bi-arrow-repeat me-2"></i>Full Sync</h5>
        </div>
        <div class="card-body">
            <div id="syncIdle">
                <p class="mb-4">
                    Click the button below to synchronize all domains, users, and aliases from Google Workspace.
                    This will:
                </p>
                <ul class="mb-4">
                    <li>Fetch all domains from Google Workspace</li>
                    <li>Add new domains (unassigned - you can assign owners later)</li>
                    <li>Sync users for each domain</li>
                    <li>Sync email aliases</li>
                </ul>

                <button type="button" class="btn btn-primary btn-lg" id="syncBtn" onclick="startFullSync()">
                    <i class="bi bi-cloud-arrow-down me-2"></i> Sync Now
                </button>
            </div>

            <!-- Progress Section -->
            <div id="syncProgress" style="display: none;">
                <div class="text-center mb-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h4 id="syncStatusText">Starting sync...</h4>
                    <p class="text-muted" id="syncDetailText">Please wait, this may take a few minutes.</p>
                </div>

                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                         id="syncProgressBar" style="width: 0%;">0%</div>
                </div>

                <div class="row text-center" id="liveStats">
                    <div class="col-md-3">
                        <h4 id="statDomains">0</h4>
                        <small class="text-muted">Domains</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="statNewDomains">0</h4>
                        <small class="text-muted">New Domains</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="statUsers">0</h4>
                        <small class="text-muted">Users Synced</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="statErrors">0</h4>
                        <small class="text-muted">Errors</small>
                    </div>
                </div>
            </div>

            <!-- Success Section -->
            <div id="syncSuccess" style="display: none;">
                <div class="text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    <h3 class="mt-3">Sync Complete!</h3>
                    <p class="text-muted" id="syncSummary"></p>

                    <div class="row justify-content-center mt-4">
                        <div class="col-md-8">
                            <table class="table table-bordered" id="syncResultsTable">
                                <tr>
                                    <td>Domains in Google</td>
                                    <td class="text-end fw-bold" id="resultTotalDomains">-</td>
                                </tr>
                                <tr>
                                    <td>New Domains Added</td>
                                    <td class="text-end fw-bold text-success" id="resultNewDomains">-</td>
                                </tr>
                                <tr>
                                    <td>Users Imported</td>
                                    <td class="text-end fw-bold text-primary" id="resultImported">-</td>
                                </tr>
                                <tr>
                                    <td>Users Updated</td>
                                    <td class="text-end fw-bold text-info" id="resultUpdated">-</td>
                                </tr>
                                <tr>
                                    <td>Errors</td>
                                    <td class="text-end fw-bold text-danger" id="resultErrors">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="domains.php" class="btn btn-primary">
                            <i class="bi bi-globe me-1"></i> View Domains
                        </a>
                        <a href="emails.php" class="btn btn-secondary">
                            <i class="bi bi-envelope me-1"></i> View Emails
                        </a>
                        <button type="button" class="btn btn-outline-primary" onclick="resetSync()">
                            <i class="bi bi-arrow-repeat me-1"></i> Sync Again
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error Section -->
            <div id="syncError" style="display: none;">
                <div class="text-center">
                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                    <h3 class="mt-3">Sync Failed</h3>
                    <p class="text-danger" id="syncErrorMessage"></p>

                    <button type="button" class="btn btn-primary mt-3" onclick="resetSync()">
                        <i class="bi bi-arrow-repeat me-1"></i> Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="content-card mt-4">
        <div class="card-header">
            <h5><i class="bi bi-info-circle me-2"></i>About Sync</h5>
        </div>
        <div class="card-body">
            <h6>What happens during sync?</h6>
            <ul>
                <li><strong>New domains</strong> are added as "unassigned" - you can assign them to clients later from the Domains page</li>
                <li><strong>Existing domains</strong> keep their current owner assignment</li>
                <li><strong>Users</strong> are imported or updated for each domain</li>
                <li><strong>Aliases</strong> are synced automatically with user data</li>
            </ul>

            <h6 class="mt-3">Rate Limiting</h6>
            <p class="text-muted mb-0">
                The sync includes automatic rate limiting and retry logic. If Google's API limits are reached,
                the system will wait and retry automatically.
            </p>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?php echo generateCSRFToken(); ?>';

function startFullSync() {
    // Show progress
    document.getElementById('syncIdle').style.display = 'none';
    document.getElementById('syncProgress').style.display = 'block';
    document.getElementById('syncSuccess').style.display = 'none';
    document.getElementById('syncError').style.display = 'none';

    // Start sync
    const formData = new FormData();
    formData.append('action', 'full_sync');
    formData.append('csrf_token', csrfToken);

    // Update status
    document.getElementById('syncStatusText').textContent = 'Syncing with Google Workspace...';
    document.getElementById('syncDetailText').textContent = 'Fetching domains and users. This may take a few minutes.';
    document.getElementById('syncProgressBar').style.width = '50%';
    document.getElementById('syncProgressBar').textContent = 'Syncing...';

    fetch('sync_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.stats);
        } else {
            showError(data.error);
        }
    })
    .catch(error => {
        showError(error.message);
    });
}

function showSuccess(stats) {
    document.getElementById('syncProgress').style.display = 'none';
    document.getElementById('syncSuccess').style.display = 'block';

    // Update stats
    document.getElementById('resultTotalDomains').textContent = stats.domains.total;
    document.getElementById('resultNewDomains').textContent = stats.domains.new;
    document.getElementById('resultImported').textContent = stats.users.imported;
    document.getElementById('resultUpdated').textContent = stats.users.updated;
    document.getElementById('resultErrors').textContent = stats.users.errors + (stats.errors ? stats.errors.length : 0);

    document.getElementById('syncSummary').textContent =
        `Synced ${stats.domains.total} domains and ${stats.users.total} users`;
}

function showError(message) {
    document.getElementById('syncProgress').style.display = 'none';
    document.getElementById('syncError').style.display = 'block';
    document.getElementById('syncErrorMessage').textContent = message;
}

function resetSync() {
    document.getElementById('syncIdle').style.display = 'block';
    document.getElementById('syncProgress').style.display = 'none';
    document.getElementById('syncSuccess').style.display = 'none';
    document.getElementById('syncError').style.display = 'none';

    // Reset progress bar
    document.getElementById('syncProgressBar').style.width = '0%';
    document.getElementById('syncProgressBar').textContent = '0%';
}
</script>

<?php include 'templates/footer.php'; ?>
