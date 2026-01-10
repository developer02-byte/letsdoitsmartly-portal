<?php
/**
 * Add last_sync_completed column to sync_locks table
 */
define('PORTAL_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain');

echo "Migration: Add last_sync_completed column\n";
echo "=========================================\n\n";

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM sync_locks LIKE 'last_sync_completed'");
if ($check && $check->num_rows > 0) {
    echo "[OK] Column already exists\n";
} else {
    // Add column
    $sql = "ALTER TABLE sync_locks ADD COLUMN last_sync_completed DATETIME NULL";
    if ($conn->query($sql)) {
        echo "[OK] Column added successfully\n";
    } else {
        echo "[ERROR] " . $conn->error . "\n";
    }
}

// Verify
echo "\nTable structure:\n";
$result = $conn->query("DESCRIBE sync_locks");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\nDone!\n";
