<?php
/**
 * Authentication Handler
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

// Start session with secure settings
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Use custom session save path in portal directory
        // cPanel's default path (/var/cpanel/php/sessions/ea-php83) is not accessible
        $custom_save_path = dirname(__DIR__) . '/sessions';
        if (is_dir($custom_save_path)) {
            session_save_path($custom_save_path);
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/portal/',  // Restrict to portal directory
            'secure' => true,      // Always use secure on HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Initialize session
initSession();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // Debug: Log session state
    error_log("isLoggedIn check - Session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", role: " . ($_SESSION['role'] ?? 'not set'));
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Require login - redirect to index if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?error=unauthorized');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($required_role) {
    requireLogin();
    if ($_SESSION['role'] !== $required_role) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Check if current user is super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

/**
 * Check if current user is client admin
 */
function isClientAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'client_admin';
}

/**
 * Authenticate user
 */
function authenticateUser($email, $password, $conn) {
    $email = trim(strtolower($email));

    $stmt = $conn->prepare("SELECT id, email, password_hash, name, role, status FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Log failed login attempt - user not found
        require_once __DIR__ . '/auth_helpers.php';
        logAuthEvent($conn, [
            'user_id' => null,
            'email_attempted' => $email,
            'auth_type' => 'failed_login',
            'status' => 'failed',
            'failure_reason' => 'Invalid email or password',
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return ['success' => false, 'error' => 'Invalid email or password'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();  // Close statement to free connection for subsequent queries

    // Check if account is active
    if ($user['status'] !== 'active') {
        // Log failed login attempt - account inactive
        require_once __DIR__ . '/auth_helpers.php';
        logAuthEvent($conn, [
            'user_id' => $user['id'],
            'email_attempted' => $email,
            'auth_type' => 'failed_login',
            'status' => 'failed',
            'failure_reason' => 'Account inactive',
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return ['success' => false, 'error' => 'Account is inactive. Contact administrator.'];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Log failed login attempt - wrong password
        require_once __DIR__ . '/auth_helpers.php';
        logAuthEvent($conn, [
            'user_id' => $user['id'],
            'email_attempted' => $email,
            'auth_type' => 'failed_login',
            'status' => 'failed',
            'failure_reason' => 'Invalid password',
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return ['success' => false, 'error' => 'Invalid email or password'];
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    // Debug: Log successful authentication
    error_log("Auth success - Session ID: " . session_id() . ", set user_id: " . $user['id'] . ", role: " . $user['role']);

    // For client admin, load their domain owner info and domains
    if ($user['role'] === 'client_admin') {
        $owner_stmt = $conn->prepare("SELECT id, company_name, admin_id FROM domain_owners WHERE user_id = ?");
        $owner_stmt->bind_param('i', $user['id']);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();

        if ($owner_result->num_rows > 0) {
            $owner = $owner_result->fetch_assoc();
            $_SESSION['domain_owner_id'] = $owner['id'];
            $_SESSION['company_name'] = $owner['company_name'];
            $_SESSION['admin_id'] = $owner['admin_id'];

            // Load allowed domains
            $domains_stmt = $conn->prepare("SELECT id, domain_name FROM domains WHERE domain_owner_id = ? AND status = 'active'");
            $domains_stmt->bind_param('i', $owner['id']);
            $domains_stmt->execute();
            $domains_result = $domains_stmt->get_result();

            $_SESSION['allowed_domains'] = [];
            while ($domain = $domains_result->fetch_assoc()) {
                $_SESSION['allowed_domains'][$domain['id']] = $domain['domain_name'];
            }
            $domains_stmt->close();
        }
        $owner_stmt->close();
    }

    // Update last login
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update_stmt->bind_param('i', $user['id']);
    $update_stmt->execute();
    $update_stmt->close();

    // Log successful login
    require_once __DIR__ . '/auth_helpers.php';
    $session_id = session_id();
    $auth_log_id = logAuthEvent($conn, [
        'user_id' => $user['id'],
        'email_attempted' => $email,
        'auth_type' => 'login',
        'status' => 'success',
        'session_id' => $session_id,
        'session_started_at' => date('Y-m-d H:i:s'),
        'ip_address' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Store auth_log_id in session for logout tracking
    $_SESSION['auth_log_id'] = $auth_log_id;

    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user's allowed domain IDs
 */
function getAllowedDomainIds() {
    if (isSuperAdmin()) {
        return null; // null means all domains
    }
    return array_keys($_SESSION['allowed_domains'] ?? []);
}
