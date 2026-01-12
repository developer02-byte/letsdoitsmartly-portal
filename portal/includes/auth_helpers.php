<?php
/**
 * Authentication Logging Helpers
 * Functions for tracking login/logout events with device info and geolocation
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Log an authentication event (login, logout, or failed login)
 */
function logAuthEvent($conn, $data) {
    try {
        // Parse user agent for device info (re-enabled)
        $device_info = parseUserAgent($data['user_agent'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO auth_logs
            (user_id, email_attempted, auth_type, status, failure_reason,
             session_id, session_started_at, ip_address, user_agent,
             device_type, browser, browser_version, os, os_version)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Failed to prepare auth_logs statement: " . $conn->error);
            return 0;
        }

        // Extract values to local variables (bind_param requires references)
        $user_id = $data['user_id'] ?? null;
        $email_attempted = $data['email_attempted'] ?? '';
        $auth_type = $data['auth_type'] ?? 'login';
        $status = $data['status'] ?? 'success';
        $failure_reason = $data['failure_reason'] ?? null;
        $session_id = $data['session_id'] ?? null;
        $session_started_at = $data['session_started_at'] ?? null;
        $ip_address = $data['ip_address'] ?? '';
        $user_agent = $data['user_agent'] ?? null;
        $device_type = $device_info['device_type'] ?? null;
        $browser = $device_info['browser'] ?? null;
        $browser_version = $device_info['browser_version'] ?? null;
        $os = $device_info['os'] ?? null;
        $os_version = $device_info['os_version'] ?? null;

        $stmt->bind_param(
            'isssssssssssss',
            $user_id,
            $email_attempted,
            $auth_type,
            $status,
            $failure_reason,
            $session_id,
            $session_started_at,
            $ip_address,
            $user_agent,
            $device_type,
            $browser,
            $browser_version,
            $os,
            $os_version
        );

        if (!$stmt->execute()) {
            error_log("Failed to execute auth_logs insert: " . $stmt->error);
            $stmt->close();
            return 0;
        }

        $insert_id = $conn->insert_id;
        $stmt->close();
        return $insert_id;
    } catch (Exception $e) {
        error_log("Exception in logAuthEvent: " . $e->getMessage());
        return 0;
    }
}

/**
 * Update auth log when user logs out (adds logout time and session duration)
 */
function updateAuthLogLogout($conn, $auth_log_id) {
    $stmt = $conn->prepare("
        UPDATE auth_logs
        SET session_ended_at = NOW(),
            session_duration_seconds = TIMESTAMPDIFF(SECOND, session_started_at, NOW())
        WHERE id = ? AND auth_type = 'login'
    ");
    $stmt->bind_param('i', $auth_log_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Parse user agent string to extract device type, browser, and OS
 */
function parseUserAgent($user_agent) {
    if (empty($user_agent)) {
        return [
            'device_type' => null,
            'browser' => null,
            'browser_version' => null,
            'os' => null
        ];
    }

    // Detect device type
    $device_type = 'desktop';
    if (preg_match('/mobile|android|iphone|ipod/i', $user_agent)) {
        $device_type = preg_match('/ipad|tablet/i', $user_agent) ? 'tablet' : 'mobile';
    }

    // Detect browser
    $browser = 'Unknown';
    $browser_version = null;

    if (preg_match('/Edg\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Edge';
        $browser_version = $matches[1] ?? null;
    } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Chrome';
        $browser_version = $matches[1] ?? null;
    } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $user_agent, $matches)) {
        $browser = 'Firefox';
        $browser_version = $matches[1] ?? null;
    } elseif (preg_match('/Safari\/([0-9\.]+)/i', $user_agent, $matches)) {
        // Check it's not Chrome (which also has Safari in UA)
        if (!preg_match('/Chrome/i', $user_agent)) {
            $browser = 'Safari';
            $browser_version = $matches[1] ?? null;
        }
    } elseif (preg_match('/MSIE|Trident/i', $user_agent)) {
        $browser = 'Internet Explorer';
    }

    // Detect OS
    $os = 'Unknown';
    $os_version = null;

    if (preg_match('/Windows NT ([0-9\.]+)/i', $user_agent, $matches)) {
        $os = 'Windows';
        $os_version = $matches[1] ?? null;
    } elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $user_agent, $matches)) {
        $os = 'macOS';
        $os_version = str_replace('_', '.', $matches[1] ?? '');
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android ([0-9\.]+)/i', $user_agent, $matches)) {
        $os = 'Android';
        $os_version = $matches[1] ?? null;
    } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $user_agent, $matches)) {
        $os = 'iOS';
        $os_version = str_replace('_', '.', $matches[1] ?? '');
    } elseif (preg_match('/iPad.*OS ([0-9_]+)/i', $user_agent, $matches)) {
        $os = 'iOS';
        $os_version = str_replace('_', '.', $matches[1] ?? '');
    }

    return [
        'device_type' => $device_type,
        'browser' => $browser,
        'browser_version' => $browser_version,
        'os' => $os,
        'os_version' => $os_version
    ];
}

/**
 * Get geolocation information from IP address using ip-api.com
 * Free tier: 45 requests per minute
 */
function getGeolocationFromIP($ip) {
    // Skip for local/private IPs
    if (empty($ip) || in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
        return null;
    }

    // Skip for private IP ranges
    if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ip)) {
        return null;
    }

    // Check session cache to avoid repeated API calls (only if session is started)
    $cache_key = "geo_$ip";
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$cache_key])) {
        return $_SESSION[$cache_key];
    }

    try {
        // Using ip-api.com (free tier, no API key required)
        $url = "http://ip-api.com/json/$ip?fields=status,country,countryCode,region,city,timezone";

        $context = stream_context_create([
            'http' => [
                'timeout' => 1,  // 1 second timeout (reduced from 2)
                'ignore_errors' => true,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $geo = [
                    'country_code' => $data['countryCode'] ?? null,
                    'country_name' => $data['country'] ?? null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                    'timezone' => $data['timezone'] ?? null
                ];

                // Cache in session for this user session (only if session is active)
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[$cache_key] = $geo;
                }
                return $geo;
            }
        }
    } catch (Exception $e) {
        error_log("Geolocation API error for IP $ip: " . $e->getMessage());
    }

    return null;
}

/**
 * Get client IP address (handles proxies and load balancers)
 * Note: Also defined in helpers.php - only define if not already available
 */
if (!function_exists('getClientIP')) {
    function getClientIP() {
        $ip = null;

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // May contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
