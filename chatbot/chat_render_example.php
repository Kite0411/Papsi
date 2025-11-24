<?php
/**
 * Example: How to integrate Papsi chatbot with Render API
 *
 * This file shows how to call the production_bot.py deployed on Render
 * from your PHP frontend on Hostinger
 *
 * Replace your existing chatbot/chat.php logic with this approach
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

// Get the JSON input from frontend
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($data['message']) ? trim($data['message']) : '';

// Validate input
if (empty($userMessage)) {
    ob_clean();
    echo json_encode([
        'reply' => 'Please describe your vehicle problem so I can help you.',
        'type' => 'error'
    ]);
    ob_end_flush();
    exit;
}

// ==================== CALL RENDER API ====================

// IMPORTANT: Replace this with your actual Render service URL
$renderApiUrl = "https://your-render-service.onrender.com/chat";

// Prepare the request
$requestData = json_encode(['message' => $userMessage]);

// Initialize cURL
$ch = curl_init($renderApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($requestData)
]);

// Set timeout (AI processing can take 30-60 seconds on free tier)
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ==================== HANDLE RESPONSE ====================

if ($httpCode === 200 && $response) {
    // Success - forward the response from Render API
    $decodedResponse = json_decode($response, true);

    if ($decodedResponse && isset($decodedResponse['reply'])) {
        ob_clean();
        echo json_encode([
            'reply' => $decodedResponse['reply'],
            'type' => 'success',
            'source' => 'ai'
        ]);
    } else {
        // Invalid JSON response
        ob_clean();
        echo json_encode([
            'reply' => 'Sorry, I received an invalid response. Please try again.',
            'type' => 'error'
        ]);
    }
} else {
    // Error handling
    error_log("Render API Error: HTTP $httpCode - $curlError");

    // Friendly error message to user
    ob_clean();
    echo json_encode([
        'reply' => "I'm having trouble connecting to my brain right now 🤖. Please try again in a moment!",
        'type' => 'error',
        'debug' => [
            'http_code' => $httpCode,
            'curl_error' => $curlError
        ]
    ]);
}

ob_end_flush();
?>
