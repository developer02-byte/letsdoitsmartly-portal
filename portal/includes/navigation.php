<?php
/**
 * Navigation Configuration
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Menu items configuration
 * icon: Bootstrap icon name (without bi- prefix)
 * label: Display text
 * url: Page URL
 * roles: Array of roles that can see this item
 * badge: Optional badge function name to call
 */
$menu_items = [
    [
        'icon' => 'speedometer2',
        'label' => 'Dashboard',
        'url' => 'dashboard.php',
        'roles' => ['super_admin', 'client_admin']
    ],
    [
        'icon' => 'envelope',
        'label' => 'Email Accounts',
        'url' => 'emails.php',
        'roles' => ['super_admin', 'client_admin']
    ],
    [
        'icon' => 'arrow-left-right',
        'label' => 'Aliases',
        'url' => 'aliases.php',
        'roles' => ['super_admin', 'client_admin']
    ],
    [
        'icon' => 'globe',
        'label' => 'Domains',
        'url' => 'domains.php',
        'roles' => ['super_admin', 'client_admin']
    ],
    [
        'icon' => 'cloud-arrow-down',
        'label' => 'Google Sync',
        'url' => 'sync.php',
        'roles' => ['super_admin']
    ],
    [
        'icon' => 'people',
        'label' => 'Users',
        'url' => 'users.php',
        'roles' => ['super_admin']
    ],
    [
        'icon' => 'clock-history',
        'label' => 'Activity Logs',
        'url' => 'activity_logs_unified.php',
        'roles' => ['super_admin', 'client_admin']
    ],
    [
        'icon' => 'exclamation-triangle',
        'label' => 'Failed Operations',
        'url' => 'failed_operations.php',
        'roles' => ['super_admin']
    ],
    [
        'icon' => 'gear',
        'label' => 'Settings',
        'url' => 'settings.php',
        'roles' => ['super_admin', 'client_admin']
    ]
];

/**
 * Get menu items for current user
 */
function getMenuItems() {
    global $menu_items;

    $role = getCurrentUserRole();
    $filtered = [];

    foreach ($menu_items as $item) {
        if (in_array($role, $item['roles'])) {
            $filtered[] = $item;
        }
    }

    return $filtered;
}

/**
 * Check if menu item is active
 */
function isMenuActive($url) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $url;
}

/**
 * Render navigation menu
 */
function renderNavigation() {
    $items = getMenuItems();
    $html = '<ul class="nav flex-column">';

    foreach ($items as $item) {
        $active = isMenuActive($item['url']) ? 'active' : '';
        $html .= '<li class="nav-item">';
        $html .= '<a class="nav-link ' . $active . '" href="' . $item['url'] . '">';
        $html .= '<i class="bi bi-' . $item['icon'] . '"></i>';
        $html .= '<span>' . $item['label'] . '</span>';
        $html .= '</a>';
        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}

/**
 * Get page title from URL
 */
function getPageTitle($url = null) {
    global $menu_items;

    if (!$url) {
        $url = basename($_SERVER['PHP_SELF']);
    }

    foreach ($menu_items as $item) {
        if ($item['url'] === $url) {
            return $item['label'];
        }
    }

    return APP_NAME;
}
