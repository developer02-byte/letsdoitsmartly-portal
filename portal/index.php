<?php
/**
 * Login Page
 * Unified Email Management Portal
 */

// DEBUG: Login debug mode - access with ?logindebug=1 and POST credentials
if (isset($_GET['logindebug']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain');

    define('PORTAL_ACCESS', true);
    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/auth.php';

    echo "=== Login Debug ===\n\n";
    echo "Session ID before auth: " . session_id() . "\n";
    echo "Session vars before: " . print_r($_SESSION, true) . "\n";
    echo "Cookies received: " . print_r($_COOKIE, true) . "\n\n";

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    echo "Attempting login for: $email\n\n";

    $result = authenticateUser($email, $password, $conn);

    echo "Auth result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$result['success']) {
        echo "Error: " . $result['error'] . "\n";
    }

    echo "\nSession ID after auth: " . session_id() . "\n";
    echo "Session vars after: " . print_r($_SESSION, true) . "\n";

    // Check what cookies will be sent
    echo "\nResponse headers:\n";
    foreach (headers_list() as $header) {
        echo "  $header\n";
    }

    exit;
}

define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        $result = authenticateUser($email, $password, $conn);

        if ($result['success']) {
            // Login is logged in auth_logs via authenticateUser()
            session_write_close();  // Ensure session is saved before redirect
            header('Location: dashboard.php');
            exit;
        } else {
            $error_message = $result['error'];
        }
    }
}

// Get error from URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $error_message = 'Please login to continue.';
            break;
        case 'session_expired':
            $error_message = 'Your session has expired. Please login again.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - <?php echo APP_NAME; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-envelope-at"></i>
        </div>
        <h1 class="login-title"><?php echo APP_NAME; ?></h1>
        <p class="login-subtitle">Sign in to your account</p>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo sanitize($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="bi bi-envelope me-1"></i> Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="Enter your email" required autofocus
                       value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="bi bi-lock me-1"></i> Password
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Enter your password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="forgot_password.php" class="text-decoration-none">
                <i class="bi bi-question-circle me-1"></i> Forgot Password?
            </a>
        </div>

        <hr class="my-4">

        <p class="text-center text-muted small mb-0">
            <i class="bi bi-shield-check me-1"></i>
            Secure admin access for <?php echo parse_url(APP_URL, PHP_URL_HOST); ?>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>
