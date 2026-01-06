<?php
/**
 * Chat Proxy - Session-Aware Version
 * Automatically passes customer_id from session to Render API
 * 
 * UPDATED VERSION - Includes all necessary components
 */

// ==================== START SESSION ====================
session_name("customer_session");
session_start();

// ==================== INCLUDE CONFIG ====================
require_once __DIR__ . '/../includes/config.php';

// ==================== ERROR HANDLING ====================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

// ==================== HEADERS ====================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== GET USER MESSAGE ====================
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($data['message']) ? trim($data['message']) : '';

if (empty($userMessage)) {
    ob_clean();
    echo json_encode(['reply' => 'Please describe your vehicle problem so I can help you.']);
    ob_end_flush();
    exit;
}

// ==================== GET CUSTOMER ID FROM SESSION ====================
$customerId = null;
$customerName = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Get customer_id from customers table using user_id
    try {
        $conn = getDBConnection();
        
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, name FROM customers WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $customerId = $row['id'];
                $customerName = $row['name'];
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        // Log error but continue (guest mode)
        error_log("Error fetching customer_id: " . $e->getMessage());
    }
}

// ==================== RENDER API CONFIGURATION ====================
$RENDER_API_URL = "https://papsi-chatbot-api-ouo1.onrender.com/chat";

// Log the request (for debugging)
$logFile = __DIR__ . '/chat_proxy.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'user_id' => $_SESSION['user_id'] ?? 'guest',
    'customer_id' => $customerId ?? 'none',
    'customer_name' => $customerName ?? 'guest',
    'message' => $userMessage
];
$logMessage = json_encode($logData) . "\n";
@file_put_contents($logFile, $logMessage, FILE_APPEND);

// ==================== PREPARE REQUEST DATA ====================
$requestData = json_encode([
    'message' => $userMessage,
    'customer_id' => $customerId  // 🔥 PASS CUSTOMER ID
]);

// ==================== CALL RENDER API ====================
$ch = curl_init($RENDER_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Customer-ID: ' . ($customerId ?: ''),  // 🔥 ALSO SEND IN HEADER
    'Content-Length: ' . strlen($requestData)
]);

// Set timeout
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log the response
$responseLog = [
    'timestamp' => date('Y-m-d H:i:s'),
    'http_code' => $httpCode,
    'response' => substr($response, 0, 200),
    'customer_id_sent' => $customerId
];
@file_put_contents($logFile, json_encode($responseLog) . "\n", FILE_APPEND);

// ==================== HANDLE RESPONSE ====================
if ($httpCode === 200 && $response) {
    // Success - forward the response
    $decodedResponse = json_decode($response, true);
    
    if ($decodedResponse && isset($decodedResponse['reply'])) {
        ob_clean();
        echo json_encode([
            'reply' => $decodedResponse['reply'],
            'type' => 'success',
            'customer_name' => $customerName,
            'customer_id' => $customerId,  // For debugging
            'debug' => [
                'customer_recognized' => $customerId ? true : false,
                'session_active' => isset($_SESSION['user_id'])
            ]
        ]);
    } else {
        // Invalid JSON response
        $errorLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => 'Invalid JSON response',
            'raw_response' => $response
        ];
        @file_put_contents($logFile, json_encode($errorLog) . "\n", FILE_APPEND);
       
        ob_clean();
        echo json_encode([
            'reply' => 'Sorry, I received an invalid response. Please try again.',
            'type' => 'error'
        ]);
    }
} else {
    // Error - log and return friendly message
    $errorLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => 'Render API Error',
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'api_url' => $RENDER_API_URL,
        'customer_id' => $customerId
    ];
    @file_put_contents($logFile, json_encode($errorLog) . "\n", FILE_APPEND);
    
    ob_clean();
    echo json_encode([
        'reply' => "I'm having trouble connecting to my brain right now 🤖. Please try again in a moment! (Error: HTTP $httpCode)",
        'type' => 'error',
        'debug' => [
            'http_code' => $httpCode,
            'error' => $curlError,
            'api_url' => $RENDER_API_URL,
            'customer_id' => $customerId
        ]
    ]);
}

ob_end_flush();
?>
