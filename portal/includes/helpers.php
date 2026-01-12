<?php
/**
 * Helper Functions
 * Unified Email Management Portal
 */

// Prevent direct access
if (!defined('PORTAL_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate random password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Generate OTP code
 */
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M d, Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Get relative time
 */
function timeAgo($datetime) {
    if (!$datetime) return 'Never';

    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';

    return formatDate($datetime);
}

/**
 * Log activity with enhanced change tracking
 */
function logActivity($conn, $action, $target_email = null, $target_domain = null, $options = []) {
    try {
        $user_id = getCurrentUserId();
        $user_role = getCurrentUserRole();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Extract new fields from options
        $action_category = $options['category'] ?? detectActionCategory($action);
        $resource_type = $options['resource_type'] ?? null;
        $resource_id = $options['resource_id'] ?? null;
        $changes_made = isset($options['changes']) ? json_encode($options['changes']) : null;

        $stmt = $conn->prepare("
            INSERT INTO activity_logs
            (user_id, user_role, action, action_category, target_email, target_domain,
             resource_type, resource_id, changes_made, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Failed to prepare activity_logs statement: " . $conn->error);
            return;
        }

        $stmt->bind_param(
            'issssssisss',
            $user_id, $user_role, $action, $action_category, $target_email,
            $target_domain, $resource_type, $resource_id, $changes_made,
            $ip_address, $user_agent
        );

        if (!$stmt->execute()) {
            error_log("Failed to execute activity_logs insert: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Exception in logActivity: " . $e->getMessage());
    }
}

/**
 * Detect action category from action string
 */
function detectActionCategory($action) {
    $action_lower = strtolower($action);
    if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'logout') !== false) {
        return 'auth';
    }
    if (strpos($action_lower, 'email') !== false) {
        return 'email';
    }
    if (strpos($action_lower, 'alias') !== false) {
        return 'alias';
    }
    if (strpos($action_lower, 'domain') !== false) {
        return 'domain';
    }
    if (strpos($action_lower, 'user') !== false) {
        return 'user';
    }
    if (strpos($action_lower, 'sync') !== false) {
        return 'sync';
    }
    if (strpos($action_lower, 'setting') !== false) {
        return 'settings';
    }
    return 'other';
}

/**
 * Send email via SMTP using PHPMailer
 */
function sendEmail($to, $subject, $body) {
    global $email_config, $smtp_config;

    // Use SMTP if enabled
    if (!empty($smtp_config['enabled'])) {
        return sendEmailSMTP($to, $subject, $body);
    }

    // Fallback to PHP mail()
    $headers = [
        'From' => $email_config['from_name'] . ' <' . $email_config['from_address'] . '>',
        'Reply-To' => $email_config['from_address'],
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];

    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }

    return mail($to, $subject, $body, $header_string);
}

/**
 * Send email via SMTP using PHPMailer
 */
function sendEmailSMTP($to, $subject, $body) {
    global $smtp_config;

    // Load PHPMailer
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = $smtp_config['debug'] ?? 0;
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = $smtp_config['encryption'] === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];

        // Recipients
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($smtp_config['from_email'], $smtp_config['from_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send OTP email with verification link
 */
function sendOTPEmail($to, $otp, $action_name, $action_details = '', $token = null) {
    $subject = APP_NAME . ' - Verification Required';

    // Build verification link if token provided
    $verify_link = '';
    $verify_button = '';
    if ($token) {
        $verify_url = APP_URL . '/verify.php?token=' . $token;
        $verify_button = "
        <p style='text-align: center; margin: 25px 0;'>
            <strong style='color: #666;'>— OR —</strong>
        </p>
        <p style='text-align: center;'>
            <a href='$verify_url'
               style='background: #667eea; color: white; padding: 14px 28px;
                      text-decoration: none; border-radius: 6px; display: inline-block;
                      font-weight: bold;'>
                Click to Confirm
            </a>
        </p>
        ";
    }

    // Action details box
    $action_box = '';
    if ($action_details) {
        $action_box = "
        <div style='background: #fff3cd; padding: 15px 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
            <strong style='color: #856404;'>Action Requested:</strong>
            <p style='margin: 10px 0 0 0; color: #856404;'>$action_details</p>
        </div>
        ";
    }

    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5;'>
        <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #333; margin-top: 0;'>" . APP_NAME . "</h2>

            $action_box

            <p style='color: #555;'>Enter this verification code in the portal:</p>
            <div style='text-align: center; margin: 25px 0;'>
                <span style='background: #f0f0f0; color: #667eea; font-size: 32px; letter-spacing: 8px;
                             padding: 15px 25px; border-radius: 8px; font-family: monospace; font-weight: bold;'>$otp</span>
            </div>

            $verify_button

            <hr style='border: none; border-top: 1px solid #eee; margin: 25px 0;'>
            <p style='color: #999; font-size: 13px; margin-bottom: 0;'>
                This code expires in 5 minutes. If you didn't request this, please ignore this email.
            </p>
        </div>
    </body>
    </html>
    ";

    return sendEmail($to, $subject, $body);
}

/**
 * Flash message helpers
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Redirect with message
 */
function redirectWith($url, $type, $message) {
    setFlash($type, $message);
    header("Location: $url");
    exit;
}

/**
 * JSON response helper
 */
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'];

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    return $ip;
}
