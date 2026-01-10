<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Google API Client...<br>";

// Since vendor is in the same directory as test.php
require_once 'vendor/autoload.php';

echo "✅ Autoload loaded successfully<br>";

try {
    $client = new Google_Client();
    echo "✅ Google_Client created successfully!<br>";
    echo "✅ Setup complete! You can now use Google APIs.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    
    // Show more debug info
    echo "PHP Version: " . PHP_VERSION . "<br>";
}
?>