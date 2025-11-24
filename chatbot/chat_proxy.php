<?php
/**
 * Chat Proxy - Routes requests from frontend to Render API
 * This file should be uploaded to Hostinger
 */

// Prevent any output before JSON response
error_reporting(0);
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
// IMPORTANT: Replace this with YOUR actual Render service URL
$RENDER_API_URL = "https://papsi-chatbot-api.onrender.com/chat";

// If you want to use environment variable or config file:
// $RENDER_API_URL = getenv('CHATBOT_API_URL') ?: "https://your-default-url.onrender.com/chat";

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
        ob_clean();
        echo json_encode([
            'reply' => 'Sorry, I received an invalid response. Please try again.',
            'type' => 'error'
        ]);
    }
} else {
    // Error - log and return friendly message
    error_log("Render API Error: HTTP $httpCode - $curlError - URL: $RENDER_API_URL");

    ob_clean();
    echo json_encode([
        'reply' => "I'm having trouble connecting to my brain right now 🤖. Please try again in a moment! (This might take 30-60 seconds on the first request if the service was sleeping)",
        'type' => 'error'
    ]);
}

ob_end_flush();
?>
