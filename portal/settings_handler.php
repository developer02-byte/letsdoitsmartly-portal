<?php
/**
 * Settings Handler
 * Handles profile and password updates
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';

requireLogin();

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: settings.php?tab=profile&error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_profile':
        updateProfile();
        break;
    case 'change_password':
        changePassword();
        break;
    default:
        header('Location: settings.php');
        exit;
}

/**
 * Update user profile
 */
function updateProfile() {
    global $conn;

    $user_id = getCurrentUserId();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    // Validate required fields
    if (empty($name) || empty($email)) {
        header('Location: settings.php?tab=profile&error=Name and email are required');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: settings.php?tab=profile&error=Invalid email format');
        exit;
    }

    // Check if email is already used by another user
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param('si', $email, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: settings.php?tab=profile&error=This email is already in use');
        exit;
    }

    // Update user
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $email, $user_id);

    if ($stmt->execute()) {
        // Update session
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        logActivity($conn, "Updated profile");
        header('Location: settings.php?tab=profile&success=profile_updated');
    } else {
        header('Location: settings.php?tab=profile&error=Failed to update profile');
    }
    exit;
}

/**
 * Change user password
 */
function changePassword() {
    global $conn;

    $user_id = getCurrentUserId();
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        header('Location: settings.php?tab=security&error=All password fields are required');
        exit;
    }

    // Validate new password length
    if (strlen($new_password) < 8) {
        header('Location: settings.php?tab=security&error=New password must be at least 8 characters');
        exit;
    }

    // Validate password confirmation
    if ($new_password !== $confirm_password) {
        header('Location: settings.php?tab=security&error=New passwords do not match');
        exit;
    }

    // Get current password hash
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Location: settings.php?tab=security&error=User not found');
        exit;
    }

    $user = $result->fetch_assoc();

    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        header('Location: settings.php?tab=security&error=Current password is incorrect');
        exit;
    }

    // Hash new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->bind_param('si', $new_hash, $user_id);

    if ($update->execute()) {
        logActivity($conn, "Changed password");
        header('Location: settings.php?tab=security&success=password_changed');
    } else {
        header('Location: settings.php?tab=security&error=Failed to change password');
    }
    exit;
}
