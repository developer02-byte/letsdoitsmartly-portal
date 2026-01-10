<?php
require_once 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Google Workspace API Test</h2>";

try {
    // Test 1: Check if credentials file exists
    $credentialsFile = 'credentials.json';
    if (!file_exists($credentialsFile)) {
        throw new Exception("❌ credentials.json not found in: " . __DIR__);
    }
    echo "✅ credentials.json found<br>";

    // Test 2: Initialize Google Client
    $client = new Google_Client();
    $client->setApplicationName('WebMyDrive Test');
    $client->setAuthConfig($credentialsFile);
    $client->setScopes([
        'https://www.googleapis.com/auth/admin.directory.user',
        'https://www.googleapis.com/auth/admin.directory.orgunit'
    ]);
    $client->setSubject('admin@webmydrive.com'); // Your admin email
    
    echo "✅ Google Client initialized<br>";

    // Test 3: Try to create service
    $service = new Google_Service_Directory($client);
    echo "✅ Google Service created<br>";

    // Test 4: Try to list users (simple API call to test connectivity)
    $users = $service->users->listUsers(['domain' => 'webmydrive.com', 'maxResults' => 1]);
    echo "✅ API Connection successful - Can list users<br>";
    
    echo "<h3 style='color: green;'>🎉 All tests passed! Google API is working correctly.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p><strong>Debug info:</strong><br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Check: 
    <ul>
        <li>credentials.json file exists and is valid</li>
        <li>Domain-wide delegation is set up correctly</li>
        <li>Admin email has proper permissions</li>
        <li>Admin SDK API is enabled</li>
    </ul>";
}
?>