<?php
// Enhanced OTP reset API with proper error handling
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

session_start();

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: $errstr in $errfile on line $errline");
    return true;
});

require_once __DIR__ . '/../includes/config.php';

if (!function_exists('getDBConnection')) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'error' => 'Service temporarily unavailable. Please try again later.',
        'code' => 'CONFIG_ERROR'
    ]);
    ob_end_flush();
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'error' => 'Unable to connect to database. Please try again later.',
        'code' => 'DB_CONNECTION_ERROR'
    ]);
    ob_end_flush();
    exit;
}

header('Content-Type: application/json');

function json_response($ok, $data = [], $status = 200) {
    ob_clean();
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data);
    ob_end_flush();
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    json_response(false, [
        'error' => 'Invalid request method.',
        'code' => 'METHOD_NOT_ALLOWED'
    ], 405);
}

$action = $_POST['action'] ?? '';
if ($action === '') {
    json_response(false, [
        'error' => 'No action specified.',
        'code' => 'MISSING_ACTION'
    ], 400);
}

// ===== REQUEST CODE =====
if ($action === 'request_code') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (!validateEmail($email)) {
        json_response(false, [
            'error' => 'Please enter a valid email address.',
            'code' => 'INVALID_EMAIL'
        ], 400);
    }
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        json_response(false, [
            'error' => 'No account found with this email address.',
            'code' => 'EMAIL_NOT_FOUND'
        ], 404);
    }
    
    $code = random_int(100000, 999999);
    $stmt = $conn->prepare("UPDATE users SET code = ? WHERE email = ?");
    $stmt->bind_param('is', $code, $email);
    $ok = $stmt->execute();
    
    if (!$ok) {
        json_response(false, [
            'error' => 'Failed to generate reset code. Please try again.',
            'code' => 'CODE_GENERATION_ERROR'
        ], 500);
    }
    
    // Try to send email
    try {
        if (!function_exists('sendEmail')) {
            json_response(false, [
                'error' => 'Email service is currently unavailable.',
                'code' => 'EMAIL_SERVICE_UNAVAILABLE'
            ], 500);
        }
        
        // Enhanced HTML email template
        $htmlMessage = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f4f4f4;
                }
                .email-container {
                    max-width: 600px;
                    margin: 40px auto;
                    background: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #e63946 0%, #d90429 100%);
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    color: #ffffff;
                    margin: 0;
                    font-size: 28px;
                    font-weight: 700;
                }
                .content {
                    padding: 40px 30px;
                }
                .content h2 {
                    color: #333333;
                    font-size: 22px;
                    margin-top: 0;
                    margin-bottom: 20px;
                }
                .content p {
                    color: #666666;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 20px;
                }
                .otp-box {
                    background: #f8f9fa;
                    border: 2px dashed #e63946;
                    border-radius: 8px;
                    padding: 30px;
                    text-align: center;
                    margin: 30px 0;
                }
                .otp-code {
                    font-size: 36px;
                    font-weight: 700;
                    color: #e63946;
                    letter-spacing: 8px;
                    margin: 10px 0;
                }
                .otp-label {
                    color: #666666;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 5px;
                }
                .warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 4px;
                }
                .warning p {
                    margin: 0;
                    color: #856404;
                    font-size: 14px;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                }
                .footer p {
                    color: #6c757d;
                    font-size: 14px;
                    margin: 5px 0;
                }
                .brand {
                    color: #e63946;
                    font-weight: 700;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello!</h2>
                    <p>We received a request to reset your password for your <span class='brand'>Papsi Paps</span> account.</p>
                    <p>Use the verification code below to complete your password reset:</p>
                    
                    <div class='otp-box'>
                        <div class='otp-label'>Your Verification Code</div>
                        <div class='otp-code'>$code</div>
                    </div>
                    
                    <div class='warning'>
                        <p>⚠️ <strong>Important:</strong> This code will expire in 10 minutes. Never share this code with anyone.</p>
                    </div>
                    
                    <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class='footer'>
                    <p><strong class='brand'>Papsi Paps Auto Repair Shop</strong></p>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p style='margin-top: 15px; color: #adb5bd; font-size: 12px;'>
                        © 2025 Papsi Paps. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $plainMessage = "Your password reset code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.\n\n- Papsi Paps Auto Repair Shop";
        
        list($sent, $err) = sendEmail(
            $email,
            'Password Reset Code - Papsi Paps',
            $htmlMessage,
            $plainMessage
        );
        
        if (!$sent) {
            json_response(false, [
                'error' => 'Failed to send verification email. Please try again.',
                'code' => 'EMAIL_SEND_ERROR',
                'details' => $err
            ], 500);
        }
    } catch (Exception $e) {
        json_response(false, [
            'error' => 'Email service error. Please try again later.',
            'code' => 'EMAIL_EXCEPTION',
            'details' => $e->getMessage()
        ], 500);
    }
    
    $_SESSION['reset_email'] = $email;
    $_SESSION['code_sent_time'] = time();
    
    json_response(true, [
        'message' => 'Verification code sent successfully! Please check your email.'
    ]);
}

// ===== VERIFY CODE =====
if ($action === 'verify_code') {
    $email = sanitizeInput($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));
    $rawCode = (string)($_POST['code'] ?? '');
    $code = preg_replace('/\D+/', '', $rawCode);
    
    if (!validateEmail($email)) {
        json_response(false, [
            'error' => 'Invalid email address.',
            'code' => 'INVALID_EMAIL'
        ], 400);
    }
    
    if (strlen($code) !== 6) {
        json_response(false, [
            'error' => 'Please enter the complete 6-digit code.',
            'code' => 'INVALID_CODE_LENGTH'
        ], 400);
    }
    
    // Check if code was sent recently (10 minutes expiry)
    if (isset($_SESSION['code_sent_time'])) {
        $elapsed = time() - $_SESSION['code_sent_time'];
        if ($elapsed > 600) { // 10 minutes
            json_response(false, [
                'error' => 'Verification code has expired. Please request a new one.',
                'code' => 'CODE_EXPIRED'
            ], 400);
        }
    }
    
    $stmt = $conn->prepare("SELECT id, TRIM(code) AS code_val FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        json_response(false, [
            'error' => 'Account not found.',
            'code' => 'EMAIL_NOT_FOUND'
        ], 404);
    }
    
    $row = $result->fetch_assoc();
    $expected = (string)($row['code_val'] ?? '');
    
    if ($expected !== $code) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            logActivity('otp_mismatch', "email=$email expected=$expected got=$code");
        }
        json_response(false, [
            'error' => 'Incorrect verification code. Please try again.',
            'code' => 'INCORRECT_CODE'
        ], 400);
    }
    
    $_SESSION['otp_verified'] = true;
    $_SESSION['reset_email'] = $email;
    
    json_response(true, [
        'message' => 'Code verified successfully!'
    ]);
}

// ===== RESET PASSWORD =====
if ($action === 'reset_password') {
    $email = sanitizeInput($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        json_response(false, [
            'error' => 'Please verify your email first.',
            'code' => 'OTP_NOT_VERIFIED'
        ], 403);
    }
    
    if (!validateEmail($email)) {
        json_response(false, [
            'error' => 'Invalid email address.',
            'code' => 'INVALID_EMAIL'
        ], 400);
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        json_response(false, [
            'error' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.',
            'code' => 'PASSWORD_TOO_SHORT'
        ], 400);
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, code = NULL WHERE email = ?");
    $stmt->bind_param('ss', $passwordHash, $email);
    $ok = $stmt->execute();
    
    if (!$ok) {
        json_response(false, [
            'error' => 'Failed to update password. Please try again.',
            'code' => 'PASSWORD_UPDATE_ERROR'
        ], 500);
    }
    
    // Send confirmation email
    try {
        $confirmHtml = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f4f4; }
                .email-container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 40px 30px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .success-icon { font-size: 64px; text-align: center; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .brand { color: #e63946; font-weight: 700; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>✓ Password Changed Successfully</h1>
                </div>
                <div class='content'>
                    <div class='success-icon'>✅</div>
                    <p>Your password for <span class='brand'>Papsi Paps</span> has been successfully changed.</p>
                    <p>If you did not make this change, please contact support immediately.</p>
                </div>
                <div class='footer'>
                    <p><strong class='brand'>Papsi Paps Auto Repair Shop</strong></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendEmail($email, 'Password Changed - Papsi Paps', $confirmHtml, 'Your password has been successfully changed.');
    } catch (Exception $e) {
        // Don't fail the request if confirmation email fails
        error_log("Failed to send confirmation email: " . $e->getMessage());
    }
    
    unset($_SESSION['otp_verified']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['code_sent_time']);
    
    json_response(true, [
        'message' => 'Password changed successfully! You can now login with your new password.'
    ]);
}

json_response(false, [
    'error' => 'Invalid action specified.',
    'code' => 'UNKNOWN_ACTION'
], 400);
