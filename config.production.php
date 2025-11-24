<?php
/**
 * Papsi Repair Shop - Production Configuration
 * This file contains production-specific settings
 * Use this on Hostinger deployment
 */

// Load environment variables (if using .env file)
// You may need to set these directly in Hostinger's control panel

// Database Configuration - UPDATE THESE IN HOSTINGER
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'u563434200_papsipaps');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'u563434200_@A');
define('DB_NAME', getenv('DB_NAME') ?: 'u563434200_papsipaps');

// Chatbot API URL - IMPORTANT: Set this to your Render service URL
define('CHATBOT_API_URL', getenv('CHATBOT_API_URL') ?: 'https://your-render-service.onrender.com');

// AI Configuration
define('HUGGING_FACE_TOKEN', getenv('HUGGING_FACE_TOKEN') ?: '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');
define('AI_MODEL_URL', 'https://api-inference.huggingface.co/models/gpt2');
define('AI_TIMEOUT', 10);

// Chatbot Configuration
define('CHATBOT_NAME', 'AutoFix Assistant');
define('CHATBOT_WELCOME_MESSAGE', 'Hi! How can I help you with your car today?');
define('CHATBOT_SIMILARITY_THRESHOLD', 4);
define('CHATBOT_MAX_RESPONSE_LENGTH', 200);

// Auto Repair Context
define('AUTO_REPAIR_CONTEXT', 'You are an AI assistant for an auto repair shop. Provide helpful, professional responses about car services, maintenance, and repairs. Keep responses concise and friendly.');

// Fallback Responses
define('FALLBACK_RESPONSES', [
    "I'm here to help with your auto repair needs! What service are you looking for?",
    "Welcome to AutoFix! I can help you with oil changes, tune-ups, air conditioning, and more. What do you need?",
    "Hi! I'm your AutoFix assistant. I can help you book services or answer questions about car maintenance.",
    "Need help with your car? I can assist with reservations, service information, and general auto repair questions."
]);

// Application Settings
define('APP_NAME', 'Papsi Repair Shop');
define('APP_VERSION', '2.0.0');
define('APP_URL', getenv('APP_URL') ?: 'https://goldenrod-quetzal-768639.hostingersite.com');

// Security Settings
define('SESSION_TIMEOUT', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', 'uploads/');

// Email Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'ritzkarldelaisla@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'exgxsyfsmbsqrldd');
define('EMAIL_DEV_MODE', false);

// Error Reporting - PRODUCTION MODE
define('DEBUG_MODE', false);
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// Timezone
date_default_timezone_set('Asia/Manila');

// Helper function to call Render chatbot API
function callChatbotAPI($endpoint, $data = [], $method = 'POST') {
    $url = rtrim(CHATBOT_API_URL, '/') . '/' . ltrim($endpoint, '/');

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => $method,
            'content' => json_encode($data),
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        error_log("Chatbot API Error: Failed to connect to $url");
        return ['error' => 'Failed to connect to chatbot service'];
    }

    return json_decode($result, true);
}

// Helper Functions (same as config.php)
function getFallbackResponse() {
    $responses = FALLBACK_RESPONSES;
    return $responses[array_rand($responses)];
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logActivity($action, $details = '') {
    $logFile = 'logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $logEntry = "[$timestamp] User: $userId, IP: $ip, Action: $action, Details: $details\n";

    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function logAudit($actionType, $description, $adminId = null, $adminUsername = null) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        INSERT INTO audit_trail (admin_id, admin_username, action_type, description, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isss", $adminId, $adminUsername, $actionType, $description);
    $stmt->execute();
    $stmt->close();
}

function getAuditTrailStats() {
    $conn = getDBConnection();
    $stats = [
        'by_action' => [],
        'hourly_activity' => []
    ];

    $query1 = "
        SELECT action_type, COUNT(*) AS count
        FROM audit_trail
        GROUP BY action_type
        ORDER BY count DESC
    ";
    $res1 = $conn->query($query1);
    if ($res1) {
        while ($row = $res1->fetch_assoc()) {
            $stats['by_action'][] = $row;
        }
        $res1->free();
    }

    $query2 = "
        SELECT HOUR(created_at) AS hour, COUNT(*) AS count
        FROM audit_trail
        WHERE created_at >= NOW() - INTERVAL 1 DAY
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ";
    $res2 = $conn->query($query2);
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $stats['hourly_activity'][] = $row;
        }
        $res2->free();
    }

    return $stats;
}

function getAuditTrail($limit = 10, $offset = 0) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT id, admin_username, action_type, description, created_at
        FROM audit_trail
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $entries[] = $row;
        }
        $result->free();
    }

    $stmt->close();
    return $entries;
}

function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if (!$conn) {
            error_log("❌ Database connection failed: " . mysqli_connect_error());
            die("Database connection error. Please try again later.");
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

if (!function_exists('sendEmail')) {
    function sendEmail($recipientEmail, $emailSubject, $emailHtmlBody, $emailTextBody = '') {
        if (defined('EMAIL_DEV_MODE') && EMAIL_DEV_MODE === true) {
            logActivity('email_dev_mode', "TO=$recipientEmail | SUBJECT=$emailSubject | BODY=" . strip_tags($emailTextBody !== '' ? $emailTextBody : $emailHtmlBody));
            return [true, 'Email dev mode: logged instead of sent'];
        }

        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        } else {
            error_log('Composer autoload not found');
            return [false, 'Email service unavailable'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = (SMTP_PORT === 465)
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_USERNAME, APP_NAME);
            $mail->addAddress($recipientEmail);

            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body = $emailHtmlBody;
            $mail->AltBody = $emailTextBody !== '' ? $emailTextBody : strip_tags($emailHtmlBody);

            $mail->send();
            return [true, 'Email sent'];
        } catch (\Throwable $e) {
            error_log('Mailer Error: ' . $e->getMessage());
            return [false, 'Failed to send email'];
        }
    }
}
?>
