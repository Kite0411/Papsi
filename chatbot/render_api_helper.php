<?php
/**
 * Papsi Repair Shop - Render API Helper
 * Helper functions to integrate PHP frontend with Flask API on Render
 */

/**
 * Call the Flask chatbot API on Render
 * @param string $message - User's message
 * @return array - Chatbot response with 'reply' key
 */
function callRenderChatbot($message) {
    // Get API URL from config
    $apiUrl = defined('CHATBOT_API_URL') ? CHATBOT_API_URL : 'http://localhost:5000';
    $url = rtrim($apiUrl, '/') . '/chat';

    $data = json_encode(['message' => $message]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        return $decoded ?: ['reply' => 'Error parsing response'];
    } else {
        // Log error
        error_log("Render API Error: HTTP $httpCode - $error - URL: $url");
        return ['reply' => "I'm having trouble connecting right now. Please try again in a moment."];
    }
}

/**
 * Get pending questions from Render API
 * @return array - List of pending questions
 */
function getRenderPendingQuestions() {
    $apiUrl = defined('CHATBOT_API_URL') ? CHATBOT_API_URL : 'http://localhost:5000';
    $url = rtrim($apiUrl, '/') . '/pending';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        return json_decode($response, true) ?: [];
    } else {
        error_log("Render API Error getting pending questions: HTTP $httpCode");
        return [];
    }
}

/**
 * Save answer to knowledge base via Render API
 * @param string $question - The question
 * @param string $answer - The answer
 * @return array - Response with 'status' and 'message'
 */
function saveRenderAnswer($question, $answer) {
    $apiUrl = defined('CHATBOT_API_URL') ? CHATBOT_API_URL : 'http://localhost:5000';
    $url = rtrim($apiUrl, '/') . '/save_answer';

    $data = json_encode([
        'question' => $question,
        'answer' => $answer
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    } else {
        error_log("Render API Error saving answer: HTTP $httpCode");
        return [
            'status' => 'error',
            'message' => 'Failed to save answer'
        ];
    }
}

/**
 * Check if Render API is healthy
 * @return bool - True if API is responding
 */
function checkRenderHealth() {
    $apiUrl = defined('CHATBOT_API_URL') ? CHATBOT_API_URL : 'http://localhost:5000';
    $url = rtrim($apiUrl, '/') . '/health';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}

/**
 * Example usage in your chat.php:
 *
 * include 'chatbot/render_api_helper.php';
 *
 * // Get user message
 * $userMessage = $_POST['message'];
 *
 * // Call Render API
 * $response = callRenderChatbot($userMessage);
 *
 * // Send response back to frontend
 * echo json_encode($response);
 */
?>
