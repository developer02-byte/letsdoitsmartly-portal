<?php
/**
 * Admin Password Reset Script
 * SECURITY: Delete this file after use!
 */

// Database credentials
$db_config = [
    'host' => 'localhost',
    'username' => 'letsdoit_portal',
    'password' => 'RH(!)hk*wK2~S(&sg)',
    'database' => 'letsdoit_portal',
    'charset' => 'utf8mb4'
];

// New password
$new_password = 'Admin@123';
$admin_email = 'admin@letsdoitsmartly.com';

// Connect to database
$conn = new mysqli(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database']
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate new password hash
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->bind_param('ss', $password_hash, $admin_email);

if ($stmt->execute()) {
    echo "<h2>✅ Password Reset Successful!</h2>";
    echo "<p>Email: <strong>$admin_email</strong></p>";
    echo "<p>Password: <strong>$new_password</strong></p>";
    echo "<p>Generated Hash: <code>$password_hash</code></p>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT: DELETE THIS FILE IMMEDIATELY FOR SECURITY!</strong></p>";
    echo "<p><a href='../index.php'>Go to Login Page</a></p>";
} else {
    echo "<h2>❌ Error updating password</h2>";
    echo "<p>" . $stmt->error . "</p>";
}

$stmt->close();
$conn->close();
?>
