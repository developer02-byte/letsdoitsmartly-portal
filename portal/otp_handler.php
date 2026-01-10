<?php
/**
 * OTP Handler
 * Handles OTP generation, sending, and verification for sensitive operations
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/helpers.php';

requireLogin();

// Handle AJAX requests
header('Content-Type: application/json');

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'send_otp':
        sendOTP();
        break;
    case 'verify_otp':
        verifyOTP();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Send OTP for verification
 */
function sendOTP() {
    global $conn, $email_config;

    $action_type = $_POST['action_type'] ?? '';
    $action_data = $_POST['action_data'] ?? '';

    // Validate action type
    $valid_actions = ['delete_email', 'delete_alias', 'delete_domain', 'delete_user', 'bulk_delete'];
    if (!in_array($action_type, $valid_actions)) {
        jsonResponse(['success' => false, 'error' => 'Invalid action type']);
    }

    $user_id = getCurrentUserId();
    $user_email = $_SESSION['email'];

    // For Client Admin, use owner_email if set (for OTP verification by domain owner)
    if (isClientAdmin()) {
        $owner_stmt = $conn->prepare("SELECT owner_email FROM domain_owners WHERE user_id = ?");
        $owner_stmt->bind_param('i', $user_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();
        if ($owner_result->num_rows > 0) {
            $owner = $owner_result->fetch_assoc();
            if (!empty($owner['owner_email'])) {
                $user_email = $owner['owner_email'];
            }
        }
    }

    // Rate limiting - max 3 OTP requests per 15 minutes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM otp_codes
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_assoc();

    if ($recent['count'] >= 3) {
        jsonResponse(['success' => false, 'error' => 'Too many OTP requests. Please wait 15 minutes.']);
    }

    // Invalidate any existing OTPs for this user and action type
    $stmt = $conn->prepare("
        UPDATE otp_codes SET verified = 1
        WHERE user_id = ? AND action_type = ? AND verified = 0
    ");
    $stmt->bind_param('is', $user_id, $action_type);
    $stmt->execute();

    // Generate OTP and verification token
    $otp = generateOTP(6);
    $verification_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + $email_config['otp_expiry']);

    // Store OTP with verification token
    $stmt = $conn->prepare("
        INSERT INTO otp_codes (user_id, otp_code, verification_token, action_type, action_data, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $action_data_json = json_encode($action_data);
    $stmt->bind_param('isssss', $user_id, $otp, $verification_token, $action_type, $action_data_json, $expires_at);

    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'error' => 'Failed to generate OTP']);
    }

    // Get readable action name and details
    $action_names = [
        'delete_email' => 'Delete Email Account',
        'delete_alias' => 'Delete Email Alias',
        'delete_domain' => 'Delete Domain',
        'delete_user' => 'Delete User Account',
        'bulk_delete' => 'Bulk Delete Operation'
    ];
    $action_name = $action_names[$action_type] ?? 'Sensitive Operation';

    // Build human-readable action details
    $action_details = buildActionDetails($action_type, $action_data);

    // Send OTP email with verification link
    $sent = sendOTPEmail($user_email, $otp, $action_name, $action_details, $verification_token);

    if ($sent) {
        // Log the OTP request
        logActivity($conn, "OTP requested for: $action_name");

        jsonResponse([
            'success' => true,
            'message' => "Verification code sent to $user_email",
            'expires_in' => $email_config['otp_expiry']
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Failed to send verification code. Please try again.']);
    }
}

/**
 * Verify OTP and return action data if valid
 */
function verifyOTP() {
    global $conn;

    $otp = trim($_POST['otp'] ?? '');
    $action_type = $_POST['action_type'] ?? '';

    if (empty($otp) || strlen($otp) !== 6) {
        jsonResponse(['success' => false, 'error' => 'Please enter a valid 6-digit code']);
    }

    $user_id = getCurrentUserId();

    // Get the OTP record
    $stmt = $conn->prepare("
        SELECT * FROM otp_codes
        WHERE user_id = ? AND action_type = ? AND otp_code = ? AND verified = 0 AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('iss', $user_id, $action_type, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Check if there's an expired or used OTP
        $stmt2 = $conn->prepare("
            SELECT * FROM otp_codes
            WHERE user_id = ? AND action_type = ? AND otp_code = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt2->bind_param('iss', $user_id, $action_type, $otp);
        $stmt2->execute();
        $expired = $stmt2->get_result();

        if ($expired->num_rows > 0) {
            $otp_record = $expired->fetch_assoc();
            if ($otp_record['verified']) {
                jsonResponse(['success' => false, 'error' => 'This code has already been used']);
            } else {
                jsonResponse(['success' => false, 'error' => 'This code has expired. Please request a new one.']);
            }
        }

        jsonResponse(['success' => false, 'error' => 'Invalid verification code']);
    }

    $otp_record = $result->fetch_assoc();

    // Mark OTP as verified/used
    $stmt = $conn->prepare("UPDATE otp_codes SET verified = 1 WHERE id = ?");
    $stmt->bind_param('i', $otp_record['id']);
    $stmt->execute();

    // Log the verification
    logActivity($conn, "OTP verified for: " . $otp_record['action_type']);

    jsonResponse([
        'success' => true,
        'message' => 'Verification successful',
        'action_data' => json_decode($otp_record['action_data'], true)
    ]);
}

/**
 * Build human-readable action details for email
 */
function buildActionDetails($action_type, $action_data) {
    if (is_string($action_data)) {
        $action_data = json_decode($action_data, true);
    }

    switch ($action_type) {
        case 'delete_email':
            $email = $action_data['email_address'] ?? $action_data['email'] ?? 'Unknown';
            return "Delete email account: <strong>$email</strong>";

        case 'delete_alias':
            $alias = $action_data['alias_address'] ?? $action_data['alias'] ?? 'Unknown';
            return "Delete email alias: <strong>$alias</strong>";

        case 'delete_domain':
            $domain = $action_data['domain_name'] ?? $action_data['domain'] ?? 'Unknown';
            return "Delete domain: <strong>$domain</strong> (and all associated emails)";

        case 'delete_user':
            $name = $action_data['user_name'] ?? $action_data['name'] ?? 'Unknown';
            return "Delete user account: <strong>$name</strong>";

        case 'bulk_delete':
            $count = $action_data['count'] ?? count($action_data['items'] ?? []);
            return "Delete <strong>$count</strong> email accounts";

        default:
            return "Perform sensitive operation";
    }
}

/**
 * Clean up expired OTPs (can be called periodically)
 */
function cleanupExpiredOTPs() {
    global $conn;

    $conn->query("DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
}
