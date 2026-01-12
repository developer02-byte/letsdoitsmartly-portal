<?php
/**
 * Database Connection File
 * Location: /home/ldsadmin/websiteconfig/dbcon.php
 * This file is outside public_html for security
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'ldsadmin_admin');
define('DB_PASSWORD', 'pymti7-narhit-myVwan');
define('DB_NAME', 'ldsadmin_email_management');

// Create connection
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("ERROR: Could not connect to database. Please contact administrator.");
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Set timezone to IST
mysqli_query($conn, "SET time_zone = '+05:30'");
?>
