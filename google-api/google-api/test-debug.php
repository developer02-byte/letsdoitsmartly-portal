<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Script started...<br>";

// Test basic PHP first
echo "PHP is working. Version: " . PHP_VERSION . "<br>";

// Test file inclusion
$vendorPath = 'google-api-php-client-2.15.0/vendor/autoload.php';
echo "Looking for: $vendorPath<br>";

if (file_exists($vendorPath)) {
    echo "✅ Vendor file exists<br>";
    require_once $vendorPath;
    echo "✅ Google Client loaded successfully<br>";
    
    $client = new Google_Client();
    echo "✅ Google_Client object created successfully!";
} else {
    echo "❌ Vendor file NOT found at: $vendorPath<br>";
    
    // List files to see what's there
    echo "Files in current directory:<br>";
    $files = scandir('.');
    foreach ($files as $file) {
        echo "- $file<br>";
    }
}
?>