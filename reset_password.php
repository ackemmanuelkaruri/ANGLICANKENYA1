<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_INCLUDED', true);


require_once 'db.php';
require_once 'includes/security.php';
require_once 'includes/email_helper.php';
require_once 'includes/scope_helpers.php'; // For CSRF functions

session_start();

$error = '';
$success = '';

// ============================================
// RATE LIMITING FUNCTION
// ============================================
function check_reset_rate_limit($identifier, $ip_address) {
    global $pdo;
    
    // Check attempts in last 15 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM password_reset_attempts 
        WHERE (identifier = ? OR ip_address = ?) 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$identifier, $ip_address]);
    $result = $stmt->fetch();
    
    // Allow max 3 attempts per 15 minutes per identifier or IP
    if ($result['attempt_count'] >= 3) {
        return false;
    }
    
    return true;
}

function log_reset_attempt($identifier, $ip_address, $success = false) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_attempts 
            (identifier, ip_address, success, attempted_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$identifier, $ip_address, $success ? 1 : 0]);
    } catch (PDOException $e) {
        error_log("Failed to log password reset attempt: " . $e->getMessage());
    }
}

// ============================================
// INPUT VALIDATION
// ============================================
function validate_identifier($identifier) {
    $identifier = trim($identifier);
    
    // Must not be empty
    if (empty($identifier)) {
        return false;
    }
    
    // Must be between 3 and 255 characters
    if (strlen($identifier) < 3 || strlen($identifier) > 255) {
        return false;
    }
    
    // Check if it looks like email, phone, or username
    // Email format
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    
    // Phone format (basic check for digits and common separators)
    if (preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $identifier)) {
        return true;
    }
    
    // Username format (alphanumeric, underscore, dash, 3-50 chars)
    if (preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $identifier)) {
        return true;
    }
    
    return false;
}

// ============================================
// MAIN FORM PROCESSING
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CSRF Protection
    verify_csrf_token();
    
    // Get client IP
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Sanitize and validate input
    $identifier = sanitize_input($_POST['identifier'] ?? '');
    
    // Validate identifier format
    if (!validate_identifier($identifier)) {
        $error = "Please enter a valid email, phone number, or username.";
    } else {
        // Check rate limiting
        if (!check_reset_rate_limit($identifier, $ip_address)) {
            $error = "Too many reset attempts. Please try again in 15 minutes.";
            log_reset_attempt($identifier, $ip_address, false);
        } else {
            try {
                // Use prepared statement to prevent SQL injection
                $stmt = $pdo->prepare("
                    SELECT id, email, first_name, username, is_active 
                    FROM users 
                    WHERE (email = ? OR phone_number = ? OR username = ?) 
                    AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier, $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // IMPORTANT: Always show success message to prevent user enumeration
                // But only send email if user actually exists
                if ($user) {
                    // Generate a secure token
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Hash the token before storing (extra security layer)
                    $token_hash = hash('sha256', $token);
                    
                    // Store hashed token in database
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password_reset_token = ?, 
                            password_reset_expires = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$token_hash, $expiry, $user['id']]);
                    
                    // Send reset email using PHPMailer helper (with plain token)
                    $sent = sendPasswordResetEmail($user['email'], $user['first_name'], $token);
                    
                    if ($sent) {
                        log_reset_attempt($identifier, $ip_address, true);
                        
                        // Log security event
                        error_log(sprintf(
                            "Password reset requested for user ID %d (email: %s) from IP: %s",
                            $user['id'],
                            $user['email'],
                            $ip_address
                        ));
                    } else {
                        // Log email failure but don't tell user
                        error_log("Failed to send password reset email to: " . $user['email']);
                    }
                } else {
                    // User doesn't exist, but don't reveal this
                    log_reset_attempt($identifier, $ip_address, false);
                    
                    // Add random delay to prevent timing attacks
                    usleep(rand(100000, 500000)); // 0.1-0.5 seconds
                }
                
                // Always show the same success message (security best practice)
                $success = "If an account exists with that information, a password reset link has been sent to the registered email address. Please check your inbox and spam folder.";
                
            } catch (PDOException $e) {
                error_log("Database error in password reset: " . $e->getMessage());
                $error = "An error occurred. Please try again later.";
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
    <title>Password Reset - Church Management System</title>
    <?php include 'includes/styles.php'; ?>
    <style>
        .reset-form {
            max-width: 500px;
            margin: 50px auto;
            background: white;
        }
        .security-note {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="reset-form border rounded p-4 shadow">
            <h2 class="text-center mb-4">Reset Password</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                
                <div class="security-note text-center">
                    <p><strong>Security Tips:</strong></p>
                    <ul class="text-start">
                        <li>The reset link is valid for 1 hour only</li>
                        <li>If you didn't request this, please ignore the email</li>
                        <li>Never share your reset link with anyone</li>
                    </ul>
                </div>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Return to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    Enter your email, phone number, or username to receive a password reset link.
                </div>
                
                <form method="POST" action="" id="resetForm">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label for="identifier" class="form-label">
                            Email, Phone Number, or Username <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="identifier" 
                            name="identifier" 
                            required 
                            maxlength="255"
                            placeholder="Enter your email, phone, or username"
                            autocomplete="username"
                        >
                        <div class="form-text">
                            We'll send a reset link to your registered email address.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
                
                <div class="security-note text-center">
                    <p class="mb-1">
                        <i class="fas fa-shield-alt"></i> 
                        <strong>Security Notice:</strong>
                    </p>
                    <p class="mb-0">
                        For your security, we limit password reset attempts. 
                        Maximum 3 attempts per 15 minutes.
                    </p>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <a href="login.php" class="btn btn-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Don't have an account? <a href="register.php">Register here</a>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Client-side validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const identifier = document.getElementById('identifier').value.trim();
            
            if (identifier.length < 3) {
                e.preventDefault();
                alert('Please enter at least 3 characters.');
                return false;
            }
        });
    </script>
</body>
</html>
