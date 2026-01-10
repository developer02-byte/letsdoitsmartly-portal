<?php
/**
 * Unified Activity Logs Page
 * 3 Tabs: Login/Logout, Sync Status, Admin Actions
 * Features: Filters, Pagination, Real-time updates, Hot/Cold storage queries
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';
require_once 'includes/navigation.php';

requireLogin();

$page_title = 'Activity Logs';
$current_tab = $_GET['tab'] ?? 'login';

// Validate tab access based on role
$allowed_tabs = ['login', 'admin'];
if (isSuperAdmin()) {
    $allowed_tabs[] = 'sync';
}

if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'login';
}

// Get users list for filter (super admin only)
$users = [];
if (isSuperAdmin()) {
    $users_result = $conn->query("SELECT id, name, email, role FROM users ORDER BY name");
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
}

// Get domains list for filter
$domains = [];
if (isSuperAdmin()) {
    $domains_result = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active' ORDER BY domain_name");
} else {
    $domain_owner_id = $_SESSION['domain_owner_id'] ?? 0;
    $domains_result = $conn->query("SELECT id, domain_name FROM domains WHERE domain_owner_id = $domain_owner_id AND status = 'active' ORDER BY domain_name");
}
while ($domain = $domains_result->fetch_assoc()) {
    $domains[] = $domain;
}

include 'templates/header.php';
?>

<?php include 'templates/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2><i class="bi bi-clock-history"></i> <?php echo $page_title; ?></h2>
    </div>

<style>
.activity-tabs .nav-link {
    border-radius: 8px 8px 0 0;
    color: #6c757d;
    font-weight: 500;
    transition: all 0.2s ease;
}

.activity-tabs .nav-link.active {
    background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-info) 100%);
    color: white;
    border: none;
}

.activity-tabs .nav-link:hover:not(.active) {
    background: #f8f9fa;
}

.drill-down-row {
    cursor: pointer;
    transition: background 0.15s ease;
}

.drill-down-row:hover {
    background: #f0f8ff !important;
}

.filter-badge {
    background: var(--bs-primary);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 5px;
}

.filter-badge .remove {
    cursor: pointer;
    font-weight: bold;
    opacity: 0.8;
}

.filter-badge .remove:hover {
    opacity: 1;
}
</style>

<div class="container-fluid py-4">
            <!-- Filters Card -->
            <div class="card mb-0">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <!-- Domain Filter -->
                        <?php if (count($domains) > 0): ?>
                        <div class="col-md-2">
                            <label class="form-label">Domain</label>
                            <select name="domain" id="domainFilter" class="form-select">
                                <option value="">All Domains</option>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['domain_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- User Filter (Super Admin Only) -->
                        <?php if (isSuperAdmin()): ?>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select name="user" id="userFilter" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']) . ' (' . $user['role'] . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Event Type Filter (for Login tab) -->
                        <div class="col-md-2" id="eventTypeFilterContainer">
                            <label class="form-label">Event Type</label>
                            <select name="auth_type" id="eventTypeFilter" class="form-select">
                                <option value="">All Events</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                                <option value="failed_login">Failed Login</option>
                            </select>
                        </div>

                        <!-- Category Filter (for Admin Actions tab) -->
                        <div class="col-md-2" id="categoryFilterContainer" style="display: none;">
                            <label class="form-label">Category</label>
                            <select name="category" id="categoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="user">Users</option>
                                <option value="domain">Domains</option>
                                <option value="email">Emails</option>
                                <option value="alias">Aliases</option>
                                <option value="sync">Sync</option>
                            </select>
                        </div>

                        <!-- Date From -->
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" id="dateFrom" class="form-control">
                        </div>

                        <!-- Date To -->
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" id="dateTo" class="form-control">
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply</button>
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary">Clear</button>
                        </div>
                    </form>

                    <!-- Active Filters Display -->
                    <div id="activeFilters" class="mt-3" style="display: none;">
                        <div class="d-flex flex-wrap" id="filterBadges"></div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs activity-tabs mb-3" id="activityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $current_tab === 'login' ? 'active' : ''; ?>"
                            id="login-tab" data-bs-toggle="tab" data-bs-target="#login-content"
                            data-tab="login" type="button" role="tab">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login / Logout
                    </button>
                </li>
                <?php if (isSuperAdmin()): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $current_tab === 'sync' ? 'active' : ''; ?>"
                            id="sync-tab" data-bs-toggle="tab" data-bs-target="#sync-content"
                            data-tab="sync" type="button" role="tab">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync Status
                    </button>
                </li>
                <?php endif; ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $current_tab === 'admin' ? 'active' : ''; ?>"
                            id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-content"
                            data-tab="admin" type="button" role="tab">
                        <i class="bi bi-activity me-1"></i> Admin Actions
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="activityTabContent">
                <!-- Login/Logout Tab -->
                <div class="tab-pane fade <?php echo $current_tab === 'login' ? 'show active' : ''; ?>"
                     id="login-content" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Authentication Logs</h5>
                        </div>
                        <div class="card-body">
                            <div id="loginLogsContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <nav id="loginPagination" class="mt-3"></nav>
                        </div>
                    </div>
                </div>

                <!-- Sync Status Tab (Super Admin Only) -->
                <?php if (isSuperAdmin()): ?>
                <div class="tab-pane fade <?php echo $current_tab === 'sync' ? 'show active' : ''; ?>"
                     id="sync-content" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Sync Operations</h5>
                        </div>
                        <div class="card-body">
                            <div id="syncLogsContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <nav id="syncPagination" class="mt-3"></nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Actions Tab -->
                <div class="tab-pane fade <?php echo $current_tab === 'admin' ? 'show active' : ''; ?>"
                     id="admin-content" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Administrative Actions</h5>
                        </div>
                        <div class="card-body">
                            <div id="adminLogsContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <nav id="adminPagination" class="mt-3"></nav>
                        </div>
                    </div>
                </div>
            </div>
</div>

<!-- Sync Details Modal -->
<div class="modal fade" id="syncDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="syncDetailsContent">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Changes Details Modal -->
<div class="modal fade" id="changesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Change Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="changesContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Activity Logs Manager
const ActivityLogs = {
    currentTab: '<?php echo $current_tab; ?>',
    currentPage: { login: 1, sync: 1, admin: 1 },
    filters: {},
    pollInterval: null,

    init() {
        this.loadFromURL();
        this.setDefaultDateRange();
        this.updateFilterVisibility(this.currentTab);
        this.bindEvents();
        this.loadTabContent(this.currentTab, 1);
    },

    setDefaultDateRange() {
        // Set default to last 7 days if not already set from URL
        if (!this.filters.date_from && !this.filters.date_to) {
            const today = new Date();
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(today.getDate() - 7);

            const formatDate = (d) => d.toISOString().split('T')[0];

            document.getElementById('dateFrom').value = formatDate(sevenDaysAgo);
            document.getElementById('dateTo').value = formatDate(today);

            this.filters.date_from = formatDate(sevenDaysAgo);
            this.filters.date_to = formatDate(today);
        }
    },

    updateFilterVisibility(tabName) {
        const eventTypeContainer = document.getElementById('eventTypeFilterContainer');
        const categoryContainer = document.getElementById('categoryFilterContainer');

        // Show event type filter only for login tab
        if (eventTypeContainer) {
            eventTypeContainer.style.display = tabName === 'login' ? 'block' : 'none';
        }

        // Show category filter only for admin tab
        if (categoryContainer) {
            categoryContainer.style.display = tabName === 'admin' ? 'block' : 'none';
        }
    },

    bindEvents() {
        // Tab switching
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const tabName = e.target.getAttribute('data-tab');
                this.switchTab(tabName);
            });
        });

        // Filter form
        document.getElementById('filterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });

        // Clear filters
        document.getElementById('clearFilters').addEventListener('click', () => {
            this.clearFilters();
        });

        // Event delegation for dynamic elements
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('view-details') || e.target.closest('.view-details')) {
                const btn = e.target.classList.contains('view-details') ? e.target : e.target.closest('.view-details');
                const syncId = btn.getAttribute('data-sync-id');
                this.showSyncDetails(syncId);
            }

            if (e.target.classList.contains('view-changes') || e.target.closest('.view-changes')) {
                const btn = e.target.classList.contains('view-changes') ? e.target : e.target.closest('.view-changes');
                const changes = btn.getAttribute('data-changes');
                this.showChanges(changes);
            }

            // Drill-down row click
            if (e.target.closest('.drill-down-row')) {
                const row = e.target.closest('.drill-down-row');
                const syncId = row.getAttribute('data-sync-id');
                if (syncId) {
                    this.showSyncDetails(syncId);
                }
            }
        });
    },

    switchTab(tabName) {
        this.currentTab = tabName;
        this.updateFilterVisibility(tabName);
        this.updateURL();
        this.loadTabContent(tabName, 1);
    },

    async loadTabContent(tab, page = 1) {
        const contentId = tab + 'LogsContent';
        const container = document.getElementById(contentId);

        if (!container) return;

        this.showLoading(container);

        try {
            const params = new URLSearchParams({
                action: `get_${tab}_logs`,
                page: page,
                ...this.filters
            });

            const response = await fetch(`activity_logs_handler.php?${params}`);
            const data = await response.json();

            if (data.success) {
                container.innerHTML = data.html;
                this.updatePagination(tab, data.page, data.total, data.per_page);

                // Start polling if sync tab has active syncs
                if (tab === 'sync' && data.has_active_syncs) {
                    this.startSyncPolling();
                } else if (tab !== 'sync') {
                    this.stopSyncPolling();
                }
            } else {
                container.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${data.error}</div>`;
            }
        } catch (error) {
            console.error('Error loading logs:', error);
            container.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Network error. Please try again.</div>`;
        }
    },

    applyFilters() {
        this.filters = this.collectFilters();
        this.currentPage[this.currentTab] = 1;
        this.updateURL();
        this.loadTabContent(this.currentTab, 1);
        this.updateActiveFilters();
    },

    collectFilters() {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        const filters = {};

        for (let [key, value] of formData.entries()) {
            if (value) filters[key] = value;
        }

        return filters;
    },

    clearFilters() {
        document.getElementById('filterForm').reset();
        this.filters = {};
        this.currentPage[this.currentTab] = 1;
        this.updateURL();
        this.loadTabContent(this.currentTab, 1);
        document.getElementById('activeFilters').style.display = 'none';
    },

    updateActiveFilters() {
        const container = document.getElementById('activeFilters');
        const badgesContainer = document.getElementById('filterBadges');

        if (Object.keys(this.filters).length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        badgesContainer.innerHTML = '';

        const filterLabels = {
            domain: 'Domain',
            user: 'User',
            date_from: 'From',
            date_to: 'To',
            auth_type: 'Type',
            status: 'Status',
            category: 'Category',
            resource_type: 'Resource',
            search: 'Search'
        };

        for (let [key, value] of Object.entries(this.filters)) {
            const badge = document.createElement('span');
            badge.className = 'filter-badge';
            badge.innerHTML = `<strong>${filterLabels[key] || key}:</strong> ${value} <span class="remove" onclick="ActivityLogs.removeFilter('${key}')">&times;</span>`;
            badgesContainer.appendChild(badge);
        }
    },

    removeFilter(key) {
        delete this.filters[key];
        const input = document.querySelector(`[name="${key}"]`);
        if (input) input.value = '';
        this.applyFilters();
    },

    updatePagination(tab, page, total, perPage) {
        const paginationId = tab + 'Pagination';
        const container = document.getElementById(paginationId);

        if (!container) return;

        const totalPages = Math.ceil(total / perPage);

        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination justify-content-center mb-0">';

        // Previous
        html += `<li class="page-item ${page === 1 ? 'disabled' : ''}">`;
        html += `<a class="page-link" href="#" onclick="ActivityLogs.changePage(${page - 1}); return false;">Previous</a>`;
        html += `</li>`;

        // Pages
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                html += `<li class="page-item ${i === page ? 'active' : ''}">`;
                html += `<a class="page-link" href="#" onclick="ActivityLogs.changePage(${i}); return false;">${i}</a>`;
                html += `</li>`;
            } else if (i === page - 3 || i === page + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Next
        html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">`;
        html += `<a class="page-link" href="#" onclick="ActivityLogs.changePage(${page + 1}); return false;">Next</a>`;
        html += `</li>`;

        html += '</ul>';
        container.innerHTML = html;
    },

    changePage(page) {
        this.currentPage[this.currentTab] = page;
        this.loadTabContent(this.currentTab, page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    async showSyncDetails(syncId) {
        try {
            const response = await fetch(`activity_logs_handler.php?action=get_sync_details&id=${syncId}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('syncDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('syncDetailsModal'));
                modal.show();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error loading sync details:', error);
            alert('Error loading sync details');
        }
    },

    showChanges(changesJson) {
        try {
            const changes = JSON.parse(changesJson);
            let html = '';

            // Check if we have before/after structure
            if (changes.before && changes.after) {
                html = '<table class="table table-sm table-bordered">';
                html += '<thead class="table-light"><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>';
                html += '<tbody>';

                // Get all fields from both before and after
                const fields = new Set([...Object.keys(changes.before), ...Object.keys(changes.after)]);

                fields.forEach(field => {
                    const before = changes.before[field] ?? '-';
                    const after = changes.after[field] ?? '-';
                    const fieldName = field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' ');

                    html += '<tr>';
                    html += `<td><strong>${fieldName}</strong></td>`;
                    html += `<td class="text-danger"><del>${this.escapeHtml(String(before))}</del></td>`;
                    html += `<td class="text-success"><strong>${this.escapeHtml(String(after))}</strong></td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
            } else {
                // Fallback to formatted JSON for other structures
                html = '<pre style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;">';
                html += this.escapeHtml(JSON.stringify(changes, null, 2));
                html += '</pre>';
            }

            document.getElementById('changesContent').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('changesModal'));
            modal.show();
        } catch (error) {
            console.error('Error parsing changes:', error);
            alert('Error displaying changes');
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    updateURL() {
        const params = new URLSearchParams({
            tab: this.currentTab,
            page: this.currentPage[this.currentTab],
            ...this.filters
        });
        window.history.replaceState({}, '', `?${params}`);
    },

    loadFromURL() {
        const params = new URLSearchParams(window.location.search);

        for (let [key, value] of params.entries()) {
            if (key !== 'tab' && key !== 'page') {
                this.filters[key] = value;

                // Set form values
                const input = document.querySelector(`[name="${key}"]`);
                if (input) input.value = value;
            }
        }

        if (Object.keys(this.filters).length > 0) {
            this.updateActiveFilters();
        }
    },

    startSyncPolling() {
        if (this.pollInterval) return;

        console.log('Starting sync polling...');
        this.pollInterval = setInterval(() => {
            if (this.currentTab === 'sync') {
                this.loadTabContent('sync', this.currentPage.sync);
            } else {
                this.stopSyncPolling();
            }
        }, 5000); // Poll every 5 seconds
    },

    stopSyncPolling() {
        if (this.pollInterval) {
            console.log('Stopping sync polling');
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    showLoading(container) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-3">Loading activity logs...</p>
            </div>
        `;
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    ActivityLogs.init();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    ActivityLogs.stopSyncPolling();
});
</script>

</div><!-- End main-content -->

<?php include 'templates/footer.php'; ?>
