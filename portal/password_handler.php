<?php
/**
 * Password Handler
 * Handles password reset requests and password changes
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: forgot_password.php?error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'request_reset':
        requestPasswordReset();
        break;
    case 'reset_password':
        resetPassword();
        break;
    default:
        header('Location: index.php');
        exit;
}

/**
 * Request password reset - sends email with reset link
 */
function requestPasswordReset() {
    global $conn, $email_config;

    $email = strtolower(trim($_POST['email'] ?? ''));

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: forgot_password.php?error=Please enter a valid email address');
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Always show success message (security - don't reveal if email exists)
    if ($result->num_rows === 0) {
        // Log the attempt but don't reveal to user
        error_log("Password reset requested for non-existent email: $email");
        header('Location: forgot_password.php?success=1');
        exit;
    }

    $user = $result->fetch_assoc();

    // Check for recent reset requests (rate limiting)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM password_resets
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_assoc();

    if ($recent['count'] >= 3) {
        // Too many requests, but still show success (security)
        error_log("Rate limit hit for password reset: {$user['email']}");
        header('Location: forgot_password.php?success=1');
        exit;
    }

    // Invalidate any existing tokens
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();

    // Generate new token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + $email_config['reset_expiry']);

    // Store reset token
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user['id'], $token, $expires_at);
    $stmt->execute();

    // Send reset email
    $reset_link = APP_URL . "/reset_password.php?token=$token";
    $sent = sendPasswordResetEmail($user['email'], $user['name'], $reset_link);

    if (!$sent) {
        error_log("Failed to send password reset email to: {$user['email']}");
    }

    header('Location: forgot_password.php?success=1');
    exit;
}

/**
 * Reset password using token
 */
function resetPassword() {
    global $conn;

    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate token
    if (empty($token)) {
        header('Location: reset_password.php?error=Invalid reset token');
        exit;
    }

    // Get token record
    $stmt = $conn->prepare("
        SELECT pr.*, u.id as user_id, u.email
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=Invalid or expired reset link. Please request a new one.');
        exit;
    }

    $reset = $result->fetch_assoc();

    // Validate password
    if (empty($password)) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=Password is required');
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=Password must be at least 8 characters');
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=Passwords do not match');
        exit;
    }

    // Update password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $password_hash, $reset['user_id']);

    if (!$stmt->execute()) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=Failed to update password. Please try again.');
        exit;
    }

    // Mark token as used
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();

    // Log the password reset
    logPasswordReset($reset['user_id'], $reset['email']);

    header('Location: reset_password.php?success=1');
    exit;
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($to, $name, $reset_link) {
    $subject = APP_NAME . ' - Password Reset Request';

    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h2 style='color: #667eea;'>" . APP_NAME . "</h2>
        </div>

        <p>Hello <strong>$name</strong>,</p>

        <p>We received a request to reset your password. Click the button below to create a new password:</p>

        <div style='text-align: center; margin: 30px 0;'>
            <a href='$reset_link' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; display: inline-block;'>
                Reset My Password
            </a>
        </div>

        <p>Or copy and paste this link into your browser:</p>
        <p style='word-break: break-all; color: #667eea;'>$reset_link</p>

        <p><strong>This link will expire in 1 hour.</strong></p>

        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>

        <p style='color: #999; font-size: 12px;'>
            If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
        </p>

        <p style='color: #999; font-size: 12px;'>
            For security, this request was received from IP: " . getClientIP() . "
        </p>
    </body>
    </html>
    ";

    return sendEmail($to, $subject, $body);
}

/**
 * Log password reset activity
 */
function logPasswordReset($user_id, $email) {
    global $conn;

    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $action = "Password reset completed";

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_role, action, target_email, ip_address, user_agent) VALUES (?, 'system', ?, ?, ?, ?)");
    $stmt->bind_param('issss', $user_id, $action, $email, $ip_address, $user_agent);
    $stmt->execute();
}
