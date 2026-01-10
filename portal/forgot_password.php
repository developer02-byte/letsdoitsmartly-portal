<?php
/**
 * Forgot Password Page
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

$page_title = 'Forgot Password';
$error = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$success = isset($_GET['success']) ? true : false;
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
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .forgot-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-card .logo i {
            font-size: 48px;
            color: #667eea;
        }
        .forgot-card h4 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .forgot-card .subtitle {
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
    </style>
</head>
<body>
    <div class="forgot-card">
        <div class="logo">
            <i class="bi bi-envelope-at"></i>
        </div>

        <?php if ($success): ?>
            <div class="text-center">
                <i class="bi bi-check-circle success-icon"></i>
                <h4 class="mt-3">Check Your Email</h4>
                <p class="text-muted">
                    If an account exists with that email address, we've sent password reset instructions.
                </p>
                <p class="text-muted">
                    The link will expire in 1 hour.
                </p>
                <a href="index.php" class="btn btn-primary mt-3">
                    <i class="bi bi-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        <?php else: ?>
            <h4>Forgot Password?</h4>
            <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form action="password_handler.php" method="POST">
                <input type="hidden" name="action" value="request_reset">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-envelope me-1"></i>Email Address
                    </label>
                    <input type="email" name="email" class="form-control"
                           placeholder="Enter your email" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-2"></i>Send Reset Link
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
</body>
</html>
