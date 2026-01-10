<?php
/**
 * User Handler
 * Handles user CRUD operations
 * Super Admin Only
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';

requireLogin();

// Only super admin can manage users
if (!isSuperAdmin()) {
    header('Location: dashboard.php?error=Access denied');
    exit;
}

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: users.php?error=Invalid security token. Please try again.');
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addUser();
        break;
    case 'edit':
        editUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'reset_password':
        resetPassword();
        break;
    default:
        header('Location: users.php');
        exit;
}

/**
 * Add new user
 */
function addUser() {
    global $conn;

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client_admin';
    $status = $_POST['status'] ?? 'active';

    // Client admin specific fields
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $admin_id = strtolower(trim($_POST['admin_id'] ?? ''));
    $owner_email = strtolower(trim($_POST['owner_email'] ?? ''));

    // Validate required fields
    if (empty($name) || empty($email) || empty($password)) {
        header('Location: users.php?error=Name, email, and password are required');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: users.php?error=Invalid email format');
        exit;
    }

    // Validate password length
    if (strlen($password) < 8) {
        header('Location: users.php?error=Password must be at least 8 characters');
        exit;
    }

    // Validate role
    if (!in_array($role, ['super_admin', 'client_admin'])) {
        $role = 'client_admin';
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // For client admin, require admin_id
    if ($role === 'client_admin' && empty($admin_id)) {
        header('Location: users.php?error=Admin ID is required for client admins');
        exit;
    }

    // Validate owner_email format if provided
    if (!empty($owner_email) && !filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: users.php?error=Invalid OTP notification email format');
        exit;
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: users.php?error=A user with this email already exists');
        exit;
    }

    // Check if admin_id already exists (for client admin)
    if ($role === 'client_admin') {
        $check_admin = $conn->prepare("SELECT id FROM domain_owners WHERE admin_id = ?");
        $check_admin->bind_param('s', $admin_id);
        $check_admin->execute();
        if ($check_admin->get_result()->num_rows > 0) {
            header('Location: users.php?error=This Admin ID is already in use');
            exit;
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $email, $password_hash, $name, $role, $status);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Create domain_owner record for client admin
        if ($role === 'client_admin') {
            $owner_email_val = empty($owner_email) ? null : $owner_email;
            $owner_stmt = $conn->prepare("INSERT INTO domain_owners (user_id, company_name, phone, admin_id, owner_email) VALUES (?, ?, ?, ?, ?)");
            $owner_stmt->bind_param('issss', $user_id, $company_name, $phone, $admin_id, $owner_email_val);
            $owner_stmt->execute();
        }

        $conn->commit();

        // Log with full details
        logActivity($conn, "Created user: $name ($email)", null, null, [
            'category' => 'user',
            'resource_type' => 'user',
            'resource_id' => $user_id,
            'changes' => [
                'action' => 'create',
                'user' => [
                    'id' => $user_id,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'company_name' => $company_name ?: null,
                    'admin_id' => $admin_id ?: null
                ]
            ]
        ]);

        header('Location: users.php?success=added');

    } catch (Exception $e) {
        $conn->rollback();
        header('Location: users.php?error=Failed to create user: ' . $e->getMessage());
    }
    exit;
}

/**
 * Edit existing user
 */
function editUser() {
    global $conn;

    $user_id = intval($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $status = $_POST['status'] ?? 'active';

    // Client admin specific fields
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $owner_email = strtolower(trim($_POST['owner_email'] ?? ''));

    if ($user_id <= 0) {
        header('Location: users.php?error=Invalid user');
        exit;
    }

    // Validate required fields
    if (empty($name) || empty($email)) {
        header('Location: users.php?error=Name and email are required');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: users.php?error=Invalid email format');
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // Validate owner_email format if provided
    if (!empty($owner_email) && !filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: users.php?error=Invalid OTP notification email format');
        exit;
    }

    // Get current user data (for comparison)
    $current = $conn->prepare("SELECT u.*, do.company_name, do.phone, do.owner_email
                               FROM users u
                               LEFT JOIN domain_owners do ON u.id = do.user_id
                               WHERE u.id = ?");
    $current->bind_param('i', $user_id);
    $current->execute();
    $result = $current->get_result();
    if ($result->num_rows === 0) {
        header('Location: users.php?error=User not found');
        exit;
    }
    $current_user = $result->fetch_assoc();
    $original_data = [
        'name' => $current_user['name'],
        'email' => $current_user['email'],
        'status' => $current_user['status'],
        'company_name' => $current_user['company_name'],
        'phone' => $current_user['phone'],
        'owner_email' => $current_user['owner_email']
    ];

    // Check if new email already exists (if changed)
    if ($email !== $current_user['email']) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param('si', $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            header('Location: users.php?error=This email is already in use');
            exit;
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update user
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, status = ? WHERE id = ?");
        $stmt->bind_param('sssi', $name, $email, $status, $user_id);
        $stmt->execute();

        // Update domain_owner for client admin
        if ($current_user['role'] === 'client_admin') {
            $owner_email_val = empty($owner_email) ? null : $owner_email;
            $owner_stmt = $conn->prepare("UPDATE domain_owners SET company_name = ?, phone = ?, owner_email = ? WHERE user_id = ?");
            $owner_stmt->bind_param('sssi', $company_name, $phone, $owner_email_val, $user_id);
            $owner_stmt->execute();
        }

        $conn->commit();

        // Build changes array (before/after)
        $changes = ['action' => 'update', 'before' => [], 'after' => []];
        $new_data = [
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'company_name' => $company_name,
            'phone' => $phone,
            'owner_email' => $owner_email
        ];
        foreach ($original_data as $field => $old_value) {
            $new_value = $new_data[$field] ?? null;
            if ($old_value !== $new_value) {
                $changes['before'][$field] = $old_value;
                $changes['after'][$field] = $new_value;
            }
        }

        logActivity($conn, "Updated user: $name ($email)", null, null, [
            'category' => 'user',
            'resource_type' => 'user',
            'resource_id' => $user_id,
            'changes' => $changes
        ]);

        header('Location: users.php?success=updated');

    } catch (Exception $e) {
        $conn->rollback();
        header('Location: users.php?error=Failed to update user: ' . $e->getMessage());
    }
    exit;
}

/**
 * Delete user
 */
function deleteUser() {
    global $conn;

    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        header('Location: users.php?error=Invalid user');
        exit;
    }

    // Cannot delete yourself
    if ($user_id === getCurrentUserId()) {
        header('Location: users.php?error=You cannot delete your own account');
        exit;
    }

    // Get user info
    $current = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
    $current->bind_param('i', $user_id);
    $current->execute();
    $result = $current->get_result();
    if ($result->num_rows === 0) {
        header('Location: users.php?error=User not found');
        exit;
    }
    $user = $result->fetch_assoc();

    // Check if client admin has domains
    if ($user['role'] === 'client_admin') {
        $domain_check = $conn->prepare("
            SELECT COUNT(*) as count FROM domains d
            JOIN domain_owners do ON d.domain_owner_id = do.id
            WHERE do.user_id = ?
        ");
        $domain_check->bind_param('i', $user_id);
        $domain_check->execute();
        $domain_count = $domain_check->get_result()->fetch_assoc()['count'];

        if ($domain_count > 0) {
            header('Location: users.php?error=Cannot delete user with assigned domains. Reassign or delete domains first.');
            exit;
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete domain_owner record (if client admin)
        $conn->query("DELETE FROM domain_owners WHERE user_id = $user_id");

        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        $conn->commit();

        logActivity($conn, "Deleted user: {$user['name']} ({$user['email']})", null, null, [
            'category' => 'user',
            'resource_type' => 'user',
            'resource_id' => $user_id,
            'changes' => [
                'action' => 'delete',
                'deleted_user' => [
                    'id' => $user_id,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]
        ]);

        header('Location: users.php?success=deleted');

    } catch (Exception $e) {
        $conn->rollback();
        header('Location: users.php?error=Failed to delete user: ' . $e->getMessage());
    }
    exit;
}

/**
 * Reset user password
 */
function resetPassword() {
    global $conn;

    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    if ($user_id <= 0) {
        header('Location: users.php?error=Invalid user');
        exit;
    }

    if (strlen($new_password) < 8) {
        header('Location: users.php?error=Password must be at least 8 characters');
        exit;
    }

    // Get user info
    $current = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $current->bind_param('i', $user_id);
    $current->execute();
    $result = $current->get_result();
    if ($result->num_rows === 0) {
        header('Location: users.php?error=User not found');
        exit;
    }
    $user = $result->fetch_assoc();

    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $password_hash, $user_id);

    if ($stmt->execute()) {
        logActivity($conn, "Reset password for user: {$user['name']} ({$user['email']})", null, null, [
            'category' => 'user',
            'resource_type' => 'user',
            'resource_id' => $user_id,
            'changes' => [
                'action' => 'password_reset',
                'user' => [
                    'id' => $user_id,
                    'name' => $user['name'],
                    'email' => $user['email']
                ]
            ]
        ]);
        header('Location: users.php?success=password_reset');
    } else {
        header('Location: users.php?error=Failed to reset password');
    }
    exit;
}
