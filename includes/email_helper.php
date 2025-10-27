<?php
/**
 * ============================================
 * EMAIL HELPER (FULL VERSION)
 * Using PHPMailer for Verification & Reset
 * ============================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

/**
 * ============================================
 * SEND VERIFICATION EMAIL
 * ============================================
 */

function sendVerificationEmail($email, $first_name, $verification_token) {
    // Add debug output
    //echo "Debug Info:<br>";
    //echo "Email: " . htmlspecialchars($email) . "<br>";
    //echo "Name: " . htmlspecialchars($first_name) . "<br>";
   // echo "Token: " . htmlspecialchars($verification_token) . "<br><br>";
    
    $mail = new PHPMailer(true);
    
    // Enable verbose debug output
    //$mail->SMTPDebug = 3;
    //$mail->Debugoutput = function($str, $level) {
    //    echo $str . "<br>";
   // };
    
    try {
        // --- Server Config ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'beniquecreations@gmail.com';       // ✓ Your Gmail
        $mail->Password   = 'ikspurkhihjaabvs';                 // ✓ Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- Sender Info ---
        $mail->setFrom('noreply@anglicankenya.local', 'Church Management System');
        $mail->addAddress($email, $first_name);
        $mail->addReplyTo('support@anglicankenya.local', 'Support Team');

        // --- Email Content ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verify Your Email Address - Church Management System';

        // Build verification link dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
     $verification_link = $protocol . '://' . $host . '/verify_email.php?token=' . urlencode($verification_token);

        $mail->Body    = getVerificationEmailTemplate($first_name, $verification_link);
        $mail->AltBody = getVerificationEmailPlainText($first_name, $verification_link);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        echo "Error: " . $mail->ErrorInfo . "<br>";
        echo "Exception: " . $e->getMessage() . "<br>";
        return false;
    }
}

/**
 * ============================================
 * SEND PASSWORD RESET EMAIL
 * ============================================
 */
function sendPasswordResetEmail($email, $first_name, $reset_token) {
    $mail = new PHPMailer(true);
    
    try {
        // --- Server Config ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'beniquecreations@gmail.com';       // 🔹 Replace with your Gmail
        $mail->Password   = 'ikspurkhihjaabvs';          // 🔹 Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- Sender Info ---
        $mail->setFrom('noreply@anglicankenya.local', 'Church Management System');
        $mail->addAddress($email, $first_name);
        $mail->addReplyTo('support@anglicankenya.local', 'Support Team');

        // --- Email Content ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Password Reset Request - Church Management System';

        // Build reset link dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
       $reset_link = $protocol . '://' . $host . '/reset_password_confirm.php?token=' . urlencode($reset_token);

        $mail->Body    = getPasswordResetEmailTemplate($first_name, $reset_link);
        $mail->AltBody = getPasswordResetEmailPlainText($first_name, $reset_link);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Password reset email failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * ============================================
 * VERIFICATION EMAIL TEMPLATE (HTML)
 * ============================================
 */
function getVerificationEmailTemplate($first_name, $verification_link) {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verify Your Email</title>
    </head>
    <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; margin:0; padding:0;'>
        <table align='center' width='600' cellpadding='0' cellspacing='0' style='background:#fff; margin-top:30px; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
            <tr>
                <td style='background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; text-align:center; padding:40px 0;'>
                    <h1 style='margin:0;'>Church Management System</h1>
                    <p style='margin:5px 0 0 0;'>Email Verification</p>
                </td>
            </tr>
            <tr>
                <td style='padding:40px; color:#333;'>
                    <h2>Hello " . htmlspecialchars($first_name) . ",</h2>
                    <p>Thank you for registering with our Church Management System. Please verify your email to activate your account.</p>
                    <p style='text-align:center; margin:30px 0;'>
                        <a href='" . htmlspecialchars($verification_link) . "' style='background:#667eea; color:#fff; text-decoration:none; padding:14px 30px; border-radius:6px; font-weight:bold; display:inline-block;'>Verify Email</a>
                    </p>
                    <p>If the button above doesn't work, copy and paste the following link in your browser:</p>
                    <p style='background:#f1f1f1; padding:10px; border-radius:4px; word-break:break-all; font-size:12px;'>" . htmlspecialchars($verification_link) . "</p>
                    <p><strong>This link will expire in 24 hours.</strong></p>
                    <p>If you didn't request this, please ignore this email.</p>
                </td>
            </tr>
            <tr>
                <td style='background:#f8f9fa; text-align:center; padding:20px; color:#777; font-size:13px;'>
                    &copy; " . date('Y') . " Anglican Church of Kenya. All rights reserved.
                </td>
            </tr>
        </table>
    </body>
    </html>";
}

/**
 * VERIFICATION EMAIL TEMPLATE (Plain Text)
 */
function getVerificationEmailPlainText($first_name, $verification_link) {
    return "Hello $first_name,

Thank you for registering with the Church Management System.

Please verify your email using the link below:
$verification_link

This link expires in 24 hours.

If you didn't request this, please ignore this email.

Thank you,
Anglican Church of Kenya
Church Management System";
}

/**
 * ============================================
 * PASSWORD RESET TEMPLATE (HTML)
 * ============================================
 */
function getPasswordResetEmailTemplate($first_name, $reset_link) {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset</title>
    </head>
    <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; margin:0; padding:0;'>
        <table align='center' width='600' cellpadding='0' cellspacing='0' style='background:#fff; margin-top:30px; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
            <tr>
                <td style='background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; text-align:center; padding:40px 0;'>
                    <h1 style='margin:0;'>Password Reset</h1>
                    <p style='margin:5px 0 0 0;'>Church Management System</p>
                </td>
            </tr>
            <tr>
                <td style='padding:40px; color:#333;'>
                    <h2>Hello " . htmlspecialchars($first_name) . ",</h2>
                    <p>We received a request to reset your password. Click the button below to reset it:</p>
                    <p style='text-align:center; margin:30px 0;'>
                        <a href='" . htmlspecialchars($reset_link) . "' style='background:#667eea; color:#fff; text-decoration:none; padding:14px 30px; border-radius:6px; font-weight:bold; display:inline-block;'>Reset Password</a>
                    </p>
                    <p>If that doesn't work, copy and paste the following link into your browser:</p>
                    <p style='background:#f1f1f1; padding:10px; border-radius:4px; word-break:break-all; font-size:12px;'>" . htmlspecialchars($reset_link) . "</p>
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    <p>If you didn't request this, just ignore this message. Your password will remain unchanged.</p>
                </td>
            </tr>
            <tr>
                <td style='background:#f8f9fa; text-align:center; padding:20px; color:#777; font-size:13px;'>
                    &copy; " . date('Y') . " Anglican Church of Kenya. All rights reserved.
                </td>
            </tr>
        </table>
    </body>
    </html>";
}

/**
 * PASSWORD RESET TEMPLATE (Plain Text)
 */
function getPasswordResetEmailPlainText($first_name, $reset_link) {
    return "Hello $first_name,

We received a request to reset your password.

Click this link to reset your password:
$reset_link

This link expires in 1 hour.

If you didn't request this, ignore this email. Your password will remain unchanged.

Thank you,
Anglican Church of Kenya
Church Management System";
}
