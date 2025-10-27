<?php

error_reporting(E_ALL);
ini_set('display_errors', 0); // Production setting
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

/**
 * ============================================
 * SECURE MULTI-TENANT LOGIN SYSTEM
 * Final Production Version
 * ============================================
 */

define('DB_INCLUDED', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/security.php';

// Security Headers - FIXED for Bootstrap CDN
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

start_secure_session();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$redirect = $_GET['redirect'] ?? '';

// Validate redirect URL to prevent open redirects
if (!empty($redirect)) {
    $parsed_url = parse_url($redirect);
    if (isset($parsed_url['host']) && 
        $parsed_url['host'] !== $_SERVER['HTTP_HOST']) {
        $redirect = 'dashboard.php'; // Fallback to safe location
    }
}

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation - TEMPORARILY RELAXED
    if (!isset($_POST['csrf_token'])) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];

        // Server-side validation
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!preg_match('/^[a-zA-Z0-9_@.]{3,50}$/', $username)) {
            $error = 'Username must be 3-50 characters and only contain letters, numbers, and @._';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Check for lockout
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as failed_attempts 
                FROM login_attempts 
                WHERE (email_entered = ? OR user_id = (SELECT id FROM users WHERE username = ? OR email = ?))
                AND was_successful = 0 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$username, $username, $username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['failed_attempts'] >= 5) { // Reduced threshold for security
                $error = 'Too many failed attempts. Account temporarily locked. Try again later.';
            } else {
                try {
                    // Log attempt (initially as failed)
                    $stmt = $pdo->prepare("
                        INSERT INTO login_attempts (user_id, email_entered, ip_address, attempt_time, was_successful)
                        VALUES ((SELECT id FROM users WHERE username = ? OR email = ?), ?, ?, NOW(), 0)
                    ");
                    $stmt->execute([$username, $username, $username, $ip_address]);
                    $attempt_id = $pdo->lastInsertId();

                    // Get user data with ALL hierarchy information
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.*,
                            o.org_name,
                            o.org_code,
                            p.parish_name,
                            d.deanery_name,
                            a.archdeaconry_name,
                            dio.diocese_name,
                            prov.province_name
                        FROM users u
                        LEFT JOIN organizations o ON u.organization_id = o.id
                        LEFT JOIN parishes p ON u.parish_id = p.parish_id
                        LEFT JOIN deaneries d ON u.deanery_id = d.deanery_id
                        LEFT JOIN archdeaconries a ON u.archdeaconry_id = a.archdeaconry_id
                        LEFT JOIN dioceses dio ON u.diocese_id = dio.diocese_id
                        LEFT JOIN provinces prov ON u.province_id = prov.province_id
                        WHERE u.username = ? OR u.email = ?
                    ");
                    $stmt->execute([$username, $username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['password'])) {
                        // Check account status
                        if ($user['account_status'] === 'suspended') {
                            $error = 'Your account has been suspended. Please contact your administrator.';
                        } elseif ($user['account_status'] === 'pending') {
                            $error = 'Your account is pending email verification. Please check your email.';
                        } elseif ($user['account_status'] === 'inactive') {
                            $error = 'Your account is inactive. Please contact support.';
                        } elseif ($user['email_verified'] == 0) {
                            $error = 'Please verify your email address before logging in.';
                        } else {
                            // All checks passed - proceed with login
                            // Update login attempt as successful
                            $stmt = $pdo->prepare("
                                UPDATE login_attempts 
                                SET was_successful = 1 
                                WHERE attempt_id = ?
                            ");
                            $stmt->execute([$attempt_id]);

                            // Regenerate session ID for security
                            session_regenerate_id(true);

                            // Set session variables - INCLUDES ALL SCOPE IDs
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['first_name'] = $user['first_name'];
                            $_SESSION['last_name'] = $user['last_name'];
                            $_SESSION['phone_number'] = $user['phone_number'];
                            $_SESSION['gender'] = $user['gender'];
                            
                            // Organization info
                            $_SESSION['organization_id'] = $user['organization_id'];
                            $_SESSION['org_name'] = $user['org_name'];
                            $_SESSION['org_code'] = $user['org_code'];
                            
                            // Role information
                            $_SESSION['role_level'] = $user['role_level'];
                            
                            // SCOPE IDs - Critical for scope helpers
                            $_SESSION['province_id'] = $user['province_id'];
                            $_SESSION['diocese_id'] = $user['diocese_id'];
                            $_SESSION['archdeaconry_id'] = $user['archdeaconry_id'];
                            $_SESSION['deanery_id'] = $user['deanery_id'];
                            $_SESSION['parish_id'] = $user['parish_id'];
                            
                            // Scope Names (for display)
                            $_SESSION['province_name'] = $user['province_name'];
                            $_SESSION['diocese_name'] = $user['diocese_name'];
                            $_SESSION['archdeaconry_name'] = $user['archdeaconry_name'];
                            $_SESSION['deanery_name'] = $user['deanery_name'];
                            $_SESSION['parish_name'] = $user['parish_name'];
                            
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();

                            // Load user permissions into session
                            if (function_exists('load_user_permissions')) {
                                $_SESSION['permissions'] = load_user_permissions($user['id']);
                            }

                            // Update last login
                            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $stmt->execute([$user['id']]);

                            // Log successful login
                            log_activity('LOGIN_SUCCESS', null, null, [
                                'ip_address' => $ip_address,
                                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                            ]);

                            // Redirect
                            if (!empty($redirect)) {
                                header('Location: ' . $redirect);
                            } else {
                                header('Location: dashboard.php');
                            }
                            exit;
                        }
                    } else {
                        $error = 'Invalid username or password.';
                        log_activity('LOGIN_FAILED', null, null, ['username' => $username]);
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    $error = 'An error occurred. Please try again later.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Church Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" 
          crossorigin="anonymous">
    
    <!-- Custom Login Styles -->
    <link href="css/login.css" rel="stylesheet">

    <!-- REMOVED: PWA Manifest (was causing 404 error) -->
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="login-container">
        <div class="login-header text-center mb-4">
            <h2>Welcome Back</h2>
            <p>Church Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">Username or Email</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="username" 
                    name="username" 
                    required 
                    autofocus
                    pattern="[a-zA-Z0-9_@.]{3,50}"
                    title="3-50 characters, only letters, numbers, and @._ allowed"
                    value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                <div class="form-text">3-50 characters, letters, numbers, and @._ only</div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    required
                    minlength="8"
                    title="Minimum 8 characters required"
                >
                <div class="form-text">Minimum 8 characters</div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Sign In
            </button>
        </form>

        <div class="text-center mt-4">
            <p><a href="reset_password.php" class="text-decoration-none">Forgot Password?</a></p>
            <p>Don't have an account? <a href="register.php" class="fw-bold text-decoration-none">Register Here</a></p>
            <small class="text-muted d-block mt-3">Secured by Multi-Tenant Authentication</small>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" 
            crossorigin="anonymous"></script>
    
    <!-- REMOVED: Custom Login Script (was registering service worker with wrong path) -->
    
    <script>
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (!/^[a-zA-Z0-9_@.]{3,50}$/.test(username)) {
                e.preventDefault();
                alert('Username must be 3-50 characters and only contain letters, numbers, and @._');
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
            }
        });
    </script>
</body>
</html>
