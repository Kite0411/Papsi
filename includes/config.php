<?php
// AutoFix Configuration File
// Centralized settings for the application

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'u563434200_papsipaps');
define('DB_PASSWORD', 'u563434200_@A');
define('DB_NAME', 'u563434200_papsipaps');

// AI Configuration
define('HUGGING_FACE_TOKEN', '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');
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
define('APP_URL', 'https://blueviolet-seahorse-808517.hostingersite.com');

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', 'uploads/');

// Email Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'ritzkarldelaisla@gmail.com');
define('SMTP_PASSWORD', 'exgxsyfsmbsqrldd');
define('EMAIL_DEV_MODE', false);

// Error Reporting
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Helper Functions
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

// Basic activity logging (not audit trail)
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

// ========================= AUDIT TRAIL HELPERS =========================

// Log a new audit entry
// --- Log Audit Trail Function ---
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


// --- Dashboard statistics ---
function getAuditTrailStats() {
    $conn = getDBConnection();
    $stats = [
        'by_action' => [],
        'hourly_activity' => []
    ];

    // 🔹 Count by action type
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

    // 🔹 Count activity by hour in last 24 hours
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

// --- Recent activity list (for dashboard feed) ---
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




// Database Connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if (!$conn) {
            die("❌ Connection failed: " . mysqli_connect_error());
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

// Email helper using PHPMailer
if (!function_exists('sendEmail')) {
    function sendEmail($recipientEmail, $emailSubject, $emailHtmlBody, $emailTextBody = '') {
        if (defined('EMAIL_DEV_MODE') && EMAIL_DEV_MODE === true) {
            logActivity('email_dev_mode', "TO=$recipientEmail | SUBJECT=$emailSubject | BODY=" . strip_tags($emailTextBody !== '' ? $emailTextBody : $emailHtmlBody));
            return [true, 'Email dev mode: logged instead of sent'];
        }

        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        } else {
            return [false, 'Composer autoload not found. Run composer install.'];
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
            return [false, 'Mailer Error: ' . $e->getMessage()];
        }
    }
}
?>
