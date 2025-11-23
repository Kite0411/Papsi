<?php
// Simple OTP reset API: actions = request_code, verify_code, reset_password
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Start output buffering to catch any stray output

session_start();

// Error handler to catch any errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't output errors, just log them
    error_log("PHP Error: $errstr in $errfile on line $errline");
    return true;
});

require_once __DIR__ . '/../includes/config.php';

// Check if config loaded successfully
if (!function_exists('getDBConnection')) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Configuration error']);
    ob_end_flush();
    exit;
}

$conn = getDBConnection();

// Check database connection
if (!$conn) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    ob_end_flush();
    exit;
}

header('Content-Type: application/json');

function json_response($ok, $data = [], $status = 200) {
    ob_clean(); // Clear any buffered output
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $data);
    ob_end_flush();
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    json_response(false, ['error' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';
if ($action === '') {
    json_response(false, ['error' => 'Missing action'], 400);
}

if ($action === 'request_code') {
    $email = sanitizeInput($_POST['email'] ?? '');
    if (!validateEmail($email)) {
        json_response(false, ['error' => 'Invalid email'], 400);
    }
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        json_response(false, ['error' => 'Email not found'], 404);
    }
    $code = random_int(111111, 999999);
    $stmt = $conn->prepare("UPDATE users SET code = ? WHERE email = ?");
    $stmt->bind_param('is', $code, $email);
    $ok = $stmt->execute();
    if (!$ok) {
        json_response(false, ['error' => 'Failed to store code'], 500);
    }
    // Try to send email
    try {
        if (!function_exists('sendEmail')) {
            json_response(false, ['error' => 'Email function not available'], 500);
        }
        
        list($sent, $err) = sendEmail(
            $email,
            'Password Reset Code',
            nl2br("Your password reset code is $code"),
            "Your password reset code is $code"
        );
        
        if (!$sent) {
            json_response(false, ['error' => 'Failed to send email: ' . $err], 500);
        }
    } catch (Exception $e) {
        json_response(false, ['error' => 'Email error: ' . $e->getMessage()], 500);
    }
    $_SESSION['reset_email'] = $email;
    json_response(true, ['message' => 'Code sent']);
}

if ($action === 'verify_code') {
    $email = sanitizeInput($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));
    $rawCode = (string)($_POST['code'] ?? '');
    $code = preg_replace('/\D+/', '', $rawCode); // keep digits only
    if (!validateEmail($email) || strlen($code) !== 6) {
        json_response(false, ['error' => 'Invalid input'], 400);
    }
    // Use TRIM in SQL to avoid hidden spaces in DB column
    $stmt = $conn->prepare("SELECT id, TRIM(code) AS code_val FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        json_response(false, ['error' => 'Email not found'], 404);
    }
    $row = $result->fetch_assoc();
    $expected = (string)($row['code_val'] ?? '');
    if ($expected !== $code) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            logActivity('otp_mismatch', "email=$email expected=$expected got=$code");
        }
        json_response(false, ['error' => 'Incorrect code'], 400);
    }
    $_SESSION['otp_verified'] = true;
    $_SESSION['reset_email'] = $email;
    json_response(true, ['message' => 'Code verified']);
}

if ($action === 'reset_password') {
    $email = sanitizeInput($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        json_response(false, ['error' => 'OTP not verified'], 403);
    }
    if (!validateEmail($email) || strlen($password) < PASSWORD_MIN_LENGTH) {
        json_response(false, ['error' => 'Invalid input'], 400);
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, code = NULL WHERE email = ?");
    $stmt->bind_param('ss', $passwordHash, $email);
    $ok = $stmt->execute();
    if (!$ok) {
        json_response(false, ['error' => 'Failed to reset password'], 500);
    }
    unset($_SESSION['otp_verified']);
    unset($_SESSION['reset_email']);
    json_response(true, ['message' => 'Password changed']);
}

json_response(false, ['error' => 'Unknown action'], 400);
