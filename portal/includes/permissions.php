<?php
/**
 * Role-Based Permissions
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Permission definitions
 * Format: 'permission_name' => ['super_admin', 'client_admin']
 */
$permissions = [
    // Dashboard
    'view_global_stats' => ['super_admin'],
    'view_own_stats' => ['super_admin', 'client_admin'],

    // Emails
    'view_all_emails' => ['super_admin'],
    'view_own_emails' => ['super_admin', 'client_admin'],
    'create_email' => ['super_admin', 'client_admin'],
    'edit_email' => ['super_admin', 'client_admin'],
    'delete_email' => ['super_admin', 'client_admin'],
    'suspend_email' => ['super_admin', 'client_admin'],
    'change_email_password' => ['super_admin', 'client_admin'],

    // Aliases
    'view_all_aliases' => ['super_admin'],
    'view_own_aliases' => ['super_admin', 'client_admin'],
    'create_alias' => ['super_admin', 'client_admin'],
    'delete_alias' => ['super_admin', 'client_admin'],

    // Domains
    'view_all_domains' => ['super_admin'],
    'view_own_domains' => ['super_admin', 'client_admin'],
    'create_domain' => ['super_admin'],
    'edit_domain' => ['super_admin'],
    'delete_domain' => ['super_admin'],

    // Users
    'view_users' => ['super_admin'],
    'create_user' => ['super_admin'],
    'edit_user' => ['super_admin'],
    'delete_user' => ['super_admin'],

    // Settings
    'view_system_settings' => ['super_admin'],
    'edit_system_settings' => ['super_admin'],
    'view_own_settings' => ['super_admin', 'client_admin'],
    'edit_own_settings' => ['super_admin', 'client_admin'],

    // Activity Logs
    'view_all_logs' => ['super_admin'],
    'view_own_logs' => ['super_admin', 'client_admin'],

    // Sync
    'sync_all_domains' => ['super_admin'],
    'sync_own_domains' => ['super_admin', 'client_admin'],
];

/**
 * Check if current user has permission
 */
function hasPermission($permission) {
    global $permissions;

    if (!isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    if (!isset($permissions[$permission])) {
        return false;
    }

    return in_array($role, $permissions[$permission]);
}

/**
 * Require permission - redirect if not allowed
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Check if user can access a specific domain
 */
function canAccessDomain($domain_id) {
    if (isSuperAdmin()) {
        return true;
    }

    $allowed = getAllowedDomainIds();
    return in_array($domain_id, $allowed);
}

/**
 * Check if user can access a specific email
 */
function canAccessEmail($email_domain_id) {
    return canAccessDomain($email_domain_id);
}

/**
 * Get WHERE clause for domain filtering
 * Returns SQL condition to filter by allowed domains
 */
function getDomainFilterSQL($domain_column = 'domain_id') {
    if (isSuperAdmin()) {
        return '1=1'; // No filter for super admin
    }

    $allowed = getAllowedDomainIds();
    if (empty($allowed)) {
        return '1=0'; // No access
    }

    $ids = implode(',', array_map('intval', $allowed));
    return "$domain_column IN ($ids)";
}

/**
 * Get allowed domains for dropdown
 */
function getAllowedDomainsForDropdown($conn) {
    if (isSuperAdmin()) {
        $result = $conn->query("SELECT id, domain_name FROM domains WHERE status = 'active' ORDER BY domain_name");
    } else {
        $allowed = getAllowedDomainIds();
        if (empty($allowed)) {
            return [];
        }
        $ids = implode(',', array_map('intval', $allowed));
        $result = $conn->query("SELECT id, domain_name FROM domains WHERE id IN ($ids) AND status = 'active' ORDER BY domain_name");
    }

    $domains = [];
    while ($row = $result->fetch_assoc()) {
        $domains[] = $row;
    }
    return $domains;
}
