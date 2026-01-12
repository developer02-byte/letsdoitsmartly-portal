<?php
/**
 * Database Connection
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

// Database Configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'letsdoit_portal',
    'password' => 'RH(!)hk*wK2~S(&sg)',
    'database' => 'letsdoit_portal',
    'charset' => 'utf8mb4'
];

// Create connection
$conn = new mysqli(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database']
);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset
$conn->set_charset($db_config['charset']);

// Set SQL mode for strict handling
$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
