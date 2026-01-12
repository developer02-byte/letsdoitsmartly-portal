<?php
/**
 * Logout Handler
 * Unified Email Management Portal
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Log the logout action before destroying session
if (isLoggedIn()) {
    // Update auth log with logout time and session duration
    if (isset($_SESSION['auth_log_id'])) {
        require_once 'includes/auth_helpers.php';
        updateAuthLogLogout($conn, $_SESSION['auth_log_id']);
    }
    // Logout is logged in auth_logs via updateAuthLogLogout()
}

// Perform logout
logoutUser();

// Redirect to login page
header('Location: index.php');
exit;
