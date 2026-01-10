<?php
/**
 * Application Configuration
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

// Application Settings
define('APP_NAME', 'Email Management Portal');
define('APP_URL', 'https://letsdoitsmartly.com/portal');
define('APP_VERSION', '2.0.0');

// Session Configuration
define('SESSION_NAME', 'portal_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Google Workspace Settings
$google_config = [
    'api_path' => '/home4/letsdoitadmin/public_html/google-api/google-api',
    'credentials_file' => '/home4/letsdoitadmin/credentials/google-credentials.json', // Outside public_html for security
    'admin_email' => 'aiadmin@letsdoitsmartly.com',
    'scopes' => [
        // Domain scope (read-only is sufficient)
        'https://www.googleapis.com/auth/admin.directory.domain.readonly',
        // User management (full access for create/update/delete)
        'https://www.googleapis.com/auth/admin.directory.user',
        // Alias management (full access for create/delete)
        'https://www.googleapis.com/auth/admin.directory.user.alias',
    ]
];

// Sync Settings
$sync_config = [
    'stale_threshold' => 3600, // 1 hour in seconds
    'batch_size' => 500,
    'timeout' => 300
];

// Email Settings (for OTP, password reset)
$email_config = [
    'from_address' => 'developer01@technodoc.in',
    'from_name' => 'Email Management Portal',
    'otp_expiry' => 300, // 5 minutes
    'reset_expiry' => 3600 // 1 hour
];

// SMTP Configuration (Gmail with App Password)
$smtp_config = [
    'enabled' => true,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'developer01@technodoc.in',
    'password' => 'lnehhtqobtqwsvuz',
    'encryption' => 'tls', // tls or ssl
    'from_email' => 'developer01@technodoc.in',
    'from_name' => 'Email Management Portal',
    'debug' => 0 // 0 = off, 1 = client, 2 = client and server
];

// Security Settings
$security_config = [
    'password_min_length' => 8,
    'max_login_attempts' => 5,
    'lockout_duration' => 900 // 15 minutes
];

// Cron Sync Configuration
$cron_config = [
    'secret_key' => 'LDS_CRON_8f4e2b9a1c7d3e6f5a0b',  // Change this to a unique secret
    'sync_interval_minutes' => 5,                      // Cron runs every 5 minutes
    'lock_timeout_minutes' => 10                       // Lock expires after 10 minutes
];
