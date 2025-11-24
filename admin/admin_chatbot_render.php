<?php
/**
 * Admin Chatbot Interface - Hostinger
 * Connects to Render API to manage pending questions
 * Upload this to: /admin/admin_chatbot.php on Hostinger
 */

session_start();

// Check if admin is logged in (adjust this to match your auth system)
if (!isset($_SESSION['admin_id'])) {
    header('Location: /auth/admin_login.php');
    exit;
}

// Render API URL
$RENDER_API_URL = "https://papsi-chatbot-api.onrender.com";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_pending') {
        // Get pending questions from Render
        $ch = curl_init($RENDER_API_URL . '/pending');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo $response;
        } else {
            echo json_encode(['error' => 'Failed to fetch pending questions']);
        }
        exit;
    }

    if ($_POST['action'] === 'submit_answer') {
        // Submit answer to Render
        $question = $_POST['question'] ?? '';
        $answer = $_POST['answer'] ?? '';

        if (empty($question) || empty($answer)) {
            echo json_encode(['error' => 'Question and answer required']);
            exit;
        }

        $data = json_encode(['message' => $answer]);

        $ch = curl_init($RENDER_API_URL . '/admin_chat');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo $response;
        } else {
            echo json_encode(['error' => 'Failed to submit answer']);
        }
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chatbot - Manage Pending Questions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #DC143C;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            padding: 20px;
            background: linear-gradient(135deg, #DC143C, #B71C1C);
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .pending-questions {
            margin-top: 30px;
        }
        .question-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: box-shadow 0.3s;
        }
        .question-card:hover {
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.1);
        }
        .question-text {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-left: 4px solid #DC143C;
            border-radius: 4px;
        }
        .answer-form {
            display: flex;
            gap: 10px;
        }
        .answer-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .answer-input:focus {
            outline: none;
            border-color: #DC143C;
            box-shadow: 0 0 0 2px rgba(220, 20, 60, 0.1);
        }
        .submit-btn {
            background: linear-gradient(135deg, #DC143C, #B71C1C);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.3);
        }
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .refresh-btn {
            background: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .refresh-btn:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Chatbot Administration</h1>
        <p class="subtitle">Answer pending customer questions to improve the chatbot</p>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="pendingCount">-</div>
                <div class="stat-label">Pending Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="answeredToday">-</div>
                <div class="stat-label">Answered Today</div>
            </div>
        </div>

        <button class="refresh-btn" onclick="loadPendingQuestions()">🔄 Refresh Questions</button>

        <div id="messageArea"></div>

        <div class="pending-questions" id="pendingQuestions">
            <div class="loading">Loading pending questions...</div>
        </div>
    </div>

    <script>
        let currentQuestionIndex = 0;
        let pendingQuestions = [];

        // Load pending questions on page load
        document.addEventListener('DOMContentLoaded', loadPendingQuestions);

        async function loadPendingQuestions() {
            const container = document.getElementById('pendingQuestions');
            const messageArea = document.getElementById('messageArea');

            container.innerHTML = '<div class="loading">Loading pending questions...</div>';
            messageArea.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('action', 'get_pending');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                pendingQuestions = data;
                document.getElementById('pendingCount').textContent = pendingQuestions.length;

                if (pendingQuestions.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <h3>No Pending Questions</h3>
                            <p>All customer questions have been answered!</p>
                        </div>
                    `;
                } else {
                    renderQuestions();
                }

            } catch (error) {
                container.innerHTML = `
                    <div class="error">
                        ❌ Error loading questions: ${error.message}
                    </div>
                `;
            }
        }

        function renderQuestions() {
            const container = document.getElementById('pendingQuestions');

            let html = '';
            pendingQuestions.forEach((item, index) => {
                const question = item.question || item;
                html += `
                    <div class="question-card">
                        <div class="question-text">
                            <strong>Question ${index + 1}:</strong> ${escapeHtml(question)}
                        </div>
                        <div class="answer-form">
                            <input
                                type="text"
                                class="answer-input"
                                id="answer-${index}"
                                placeholder="Type your answer here..."
                                onkeypress="if(event.key==='Enter') submitAnswer(${index}, '${escapeHtml(question).replace(/'/g, "\\'")}')">
                            <button
                                class="submit-btn"
                                onclick="submitAnswer(${index}, '${escapeHtml(question).replace(/'/g, "\\'")}')">
                                Submit Answer
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function submitAnswer(index, question) {
            const answerInput = document.getElementById(`answer-${index}`);
            const answer = answerInput.value.trim();
            const messageArea = document.getElementById('messageArea');

            if (!answer) {
                messageArea.innerHTML = '<div class="error">Please enter an answer!</div>';
                return;
            }

            // Disable button
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            try {
                const formData = new FormData();
                formData.append('action', 'submit_answer');
                formData.append('question', question);
                formData.append('answer', answer);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                // Success!
                messageArea.innerHTML = `
                    <div class="success">
                        ✅ Answer saved successfully! The chatbot will now use this answer.
                    </div>
                `;

                // Reload questions after 1 second
                setTimeout(loadPendingQuestions, 1000);

            } catch (error) {
                messageArea.innerHTML = `
                    <div class="error">
                        ❌ Error: ${error.message}
                    </div>
                `;
                btn.disabled = false;
                btn.textContent = 'Submit Answer';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
```