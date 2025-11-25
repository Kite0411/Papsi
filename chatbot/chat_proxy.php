<?php
/**
 * Chat Proxy - Routes requests from frontend to Render API
 * FIXED VERSION - Better error handling and logging
 */
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// Get user message from frontend
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($data['message']) ? trim($data['message']) : '';
if (empty($userMessage)) {
    ob_clean();
    echo json_encode(['reply' => 'Please describe your vehicle problem so I can help you.']);
    ob_end_flush();
    exit;
}
// ==================== RENDER API CONFIGURATION ====================
// 🔥 CRITICAL: Your actual Render service URL
$RENDER_API_URL = "https://papsi-chatbot-api.onrender.com/chat";
// Log the request (for debugging)
$logFile = __DIR__ . '/chat_proxy.log';
$logMessage = date('Y-m-d H:i:s') . " - User Message: $userMessage\n";
@file_put_contents($logFile, $logMessage, FILE_APPEND);
// ==================== CALL RENDER API ====================
$requestData = json_encode(['message' => $userMessage]);
$ch = curl_init($RENDER_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($requestData)
]);
// Set timeout (AI processing can take 30-60 seconds on free tier first request)
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
// Log the response (for debugging)
$logMessage = date('Y-m-d H:i:s') . " - HTTP Code: $httpCode - Response: " . substr($response, 0, 200) . "\n";
@file_put_contents($logFile, $logMessage, FILE_APPEND);
// ==================== HANDLE RESPONSE ====================
if ($httpCode === 200 && $response) {
    // Success - forward the response
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse && isset($decodedResponse['reply'])) {
        ob_clean();
        echo json_encode([
            'reply' => $decodedResponse['reply'],
            'type' => 'success'
        ]);
    } else {
        // Invalid JSON response
        $logMessage = date('Y-m-d H:i:s') . " - Invalid JSON Response: $response\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
       
        ob_clean();
        echo json_encode([
            'reply' => 'Sorry, I received an invalid response. Please try again.',
            'type' => 'error'
        ]);
    }
} else {
    // Error - log and return friendly message
    $errorLog = date('Y-m-d H:i:s') . " - Render API Error: HTTP $httpCode - $curlError - URL: $RENDER_API_URL\n";
    @file_put_contents($logFile, $errorLog, FILE_APPEND);
    ob_clean();
    echo json_encode([
        'reply' => "I'm having trouble connecting to my brain right now 🤖. Please try again in a moment! (Error: HTTP $httpCode)",
        'type' => 'error',
        'debug' => [
            'http_code' => $httpCode,
            'error' => $curlError,
            'api_url' => $RENDER_API_URL
        ]
    ]);
}
ob_end_flush();
?>
