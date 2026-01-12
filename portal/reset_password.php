<?php
/**
 * Reset Password Page
 * Unified Email Management Portal
 */

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Reset Password';
$error = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$success = isset($_GET['success']) ? true : false;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validate token if not success page
$valid_token = false;
$user_email = '';

if (!$success && $token) {
    $stmt = $conn->prepare("
        SELECT pr.*, u.email
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $reset = $result->fetch_assoc();
        $valid_token = true;
        $user_email = $reset['email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-gradient);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .reset-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-card .logo i {
            font-size: 48px;
            color: #667eea;
        }
        .reset-card h4 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .reset-card .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .success-icon {
            font-size: 64px;
            color: #28a745;
        }
        .error-icon {
            font-size: 64px;
            color: #dc3545;
        }
        .input-group-text {
            cursor: pointer;
            background: white;
            border-left: none;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
        }
        .password-requirements li {
            margin-bottom: 2px;
        }
        .password-requirements .valid {
            color: #28a745;
        }
        .password-requirements .invalid {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="logo">
            <i class="bi bi-key"></i>
        </div>

        <?php if ($success): ?>
            <div class="text-center">
                <i class="bi bi-check-circle success-icon"></i>
                <h4 class="mt-3">Password Reset Successful</h4>
                <p class="text-muted">
                    Your password has been changed successfully. You can now log in with your new password.
                </p>
                <a href="index.php" class="btn btn-primary mt-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                </a>
            </div>
        <?php elseif (!$token): ?>
            <div class="text-center">
                <i class="bi bi-exclamation-triangle error-icon"></i>
                <h4 class="mt-3">Missing Token</h4>
                <p class="text-muted">
                    No reset token provided. Please use the link from your email.
                </p>
                <a href="forgot_password.php" class="btn btn-primary mt-3">
                    <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                </a>
            </div>
        <?php elseif (!$valid_token): ?>
            <div class="text-center">
                <i class="bi bi-exclamation-triangle error-icon"></i>
                <h4 class="mt-3">Invalid or Expired Link</h4>
                <p class="text-muted">
                    This password reset link is invalid or has expired. Please request a new one.
                </p>
                <a href="forgot_password.php" class="btn btn-primary mt-3">
                    <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                </a>
            </div>
        <?php else: ?>
            <h4>Reset Your Password</h4>
            <p class="subtitle">Enter a new password for <strong><?php echo sanitize($user_email); ?></strong></p>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form action="password_handler.php" method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-lock me-1"></i>New Password
                    </label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="Enter new password" required minlength="8">
                        <span class="input-group-text" onclick="togglePassword('password')">
                            <i class="bi bi-eye" id="password-icon"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-lock-fill me-1"></i>Confirm Password
                    </label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                               placeholder="Confirm new password" required minlength="8">
                        <span class="input-group-text" onclick="togglePassword('confirm_password')">
                            <i class="bi bi-eye" id="confirm_password-icon"></i>
                        </span>
                    </div>
                </div>

                <ul class="password-requirements mb-4">
                    <li id="req-length" class="invalid"><i class="bi bi-x-circle"></i> At least 8 characters</li>
                    <li id="req-match" class="invalid"><i class="bi bi-x-circle"></i> Passwords match</li>
                </ul>

                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="bi bi-check-lg me-2"></i>Reset Password
                </button>
            </form>

            <a href="index.php" class="back-link">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        <?php endif; ?>

        <hr class="my-4">
        <p class="text-center text-muted mb-0">
            <small><i class="bi bi-shield-check me-1"></i>Secure admin access for <?php echo parse_url(APP_URL, PHP_URL_HOST); ?></small>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');

        function validatePassword() {
            let valid = true;

            // Check length
            if (password.value.length >= 8) {
                reqLength.className = 'valid';
                reqLength.innerHTML = '<i class="bi bi-check-circle"></i> At least 8 characters';
            } else {
                reqLength.className = 'invalid';
                reqLength.innerHTML = '<i class="bi bi-x-circle"></i> At least 8 characters';
                valid = false;
            }

            // Check match
            if (password.value && confirmPassword.value && password.value === confirmPassword.value) {
                reqMatch.className = 'valid';
                reqMatch.innerHTML = '<i class="bi bi-check-circle"></i> Passwords match';
            } else {
                reqMatch.className = 'invalid';
                reqMatch.innerHTML = '<i class="bi bi-x-circle"></i> Passwords match';
                valid = false;
            }

            submitBtn.disabled = !valid;
        }

        if (password && confirmPassword) {
            password.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        }
    </script>
</body>
</html>
