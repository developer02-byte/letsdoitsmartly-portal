<?php
/**
 * Email Management Portal Configuration
 * Location: /home/ldsadmin/websiteconfig/config.php
 * This file contains all configuration settings
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

// Configuration Array
$config = [
    // Google Workspace Settings
    'google' => [
        'api_path' => '/home/ldsadmin/websiteconfig/google-api',
        'credentials_file' => '/home/ldsadmin/websiteconfig/google-api/credentials.json',
        'admin_email' => 'admin@letsdoitsmartly.com',
        'scopes' => [
    'https://www.googleapis.com/auth/admin.directory.user',
    'https://www.googleapis.com/auth/admin.directory.orgunit',
    'https://www.googleapis.com/auth/admin.reports.usage.readonly',
    'https://www.googleapis.com/auth/admin.directory.user.alias',
    'https://www.googleapis.com/auth/admin.directory.user.alias.readonly',
    'https://www.googleapis.com/auth/admin.directory.domain',
    'https://www.googleapis.com/auth/admin.directory.domain.readonly'

        ]
    ],
    
    // Site Settings
    'site' => [
        'name' => 'Email Management Portal',
        'url' => 'https://letsdoitsmartly.com',
        'timezone' => 'Asia/Kolkata'
    ],
    
    // Import Limits
    'import' => [
        'daily_limit' => 50,  // Maximum imports per user per day
        'timeout' => 300,    // API timeout in seconds (5 minutes)
        'batch_size' => 500  // Process users in batches
    ],
    
    // Email Settings (for OTP)
    'email' => [
        'from_address' => 'noreply@letsdoitsmartly.com',
        'from_name' => 'Email Management Portal',
        'otp_expiry' => 600  // OTP expiry in seconds (10 minutes)
    ],
    
    // Storage Conversion
    'storage' => [
        'bytes_to_gb' => 1073741824  // 1 GB = 1073741824 bytes
    ]
];

// Set timezone
date_default_timezone_set($config['site']['timezone']);
?>
