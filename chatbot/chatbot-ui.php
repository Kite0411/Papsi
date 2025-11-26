<?php
/**
 * Papsi Repair Shop - Chatbot UI with Real-time Admin Replies
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papsi Repair Shop - AI Assistant</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .chat-container {
            width: 100%;
            max-width: 800px;
            height: 80vh;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .chat-header h1 {
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .chat-header p {
            opacity: 0.8;
            font-size: 0.9em;
        }

        .status-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8em;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2ecc71;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }

        .user .avatar {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .bot .avatar {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .admin .avatar {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.4;
        }

        .user .message-content {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .bot .message-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 4px;
        }

        .admin .message-content {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-bottom-left-radius: 4px;
        }

        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .chat-input-form {
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .chat-input:focus {
            border-color: #667eea;
        }

        .send-button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s;
        }

        .send-button:hover {
            transform: translateY(-2px);
        }

        .send-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            margin-bottom: 15px;
            align-items: center;
            gap: 8px;
            max-width: 70%;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #999;
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2ecc71;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .service-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .service-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .service-desc {
            color: #666;
            margin-bottom: 8px;
            font-size: 0.9em;
        }

        .service-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #888;
        }

        @media (max-width: 768px) {
            .chat-container {
                height: 90vh;
                border-radius: 15px;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .chat-header h1 {
                font-size: 1.3em;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1>🔧 Papsi Repair Shop</h1>
            <p>AI Assistant - How can I help with your vehicle today?</p>
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Real-time Active</span>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="message bot">
                <div class="avatar">🤖</div>
                <div class="message-content">
                    Hello! I'm your Papsi Repair Assistant. I can help you with:
                    <br>• Vehicle diagnostics & repairs
                    <br>• Service recommendations  
                    <br>• Pricing & duration estimates
                    <br>• Technical advice
                    <br><br>What seems to be the issue with your vehicle?
                </div>
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
            <span>Papsi Assistant is typing...</span>
        </div>

        <div class="chat-input-container">
            <form class="chat-input-form" id="chatForm">
                <input 
                    type="text" 
                    class="chat-input" 
                    id="messageInput"
                    placeholder="Describe your vehicle problem (e.g., 'My car is making noise when braking')"
                    autocomplete="off"
                >
                <button type="submit" class="send-button" id="sendButton">
                    Send
                </button>
            </form>
        </div>
    </div>

    <script>
        // SSE Listener for real-time admin replies
        function setupSSEListener() {
            const eventSource = new EventSource('/stream');
            
            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    const notification = JSON.parse(data.message);
                    
                    console.log('SSE received:', notification);
                    
                    if (notification.type === 'admin_reply') {
                        showAdminReply(notification);
                        showNotification('🎉 Admin has replied to your question!');
                    }
                } catch (e) {
                    console.log('SSE raw message:', event.data);
                }
            };
            
            eventSource.onerror = function(event) {
                console.error('SSE error:', event);
                // Attempt to reconnect after 5 seconds
                setTimeout(setupSSEListener, 5000);
            };
            
            return eventSource;
        }

        function showAdminReply(notification) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message admin';
            messageDiv.innerHTML = `
                <div class="avatar">👨‍🔧</div>
                <div class="message-content">
                    <strong>Admin Response:</strong><br>
                    ${notification.admin_answer}
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function showNotification(message) {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => notification.classList.add('show'), 100);

            // Remove after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        // Store user's pending questions
        function storePendingQuestion(question) {
            const pendingQuestions = JSON.parse(localStorage.getItem('pendingQuestions') || '[]');
            if (!pendingQuestions.includes(question)) {
                pendingQuestions.push(question);
                localStorage.setItem('pendingQuestions', JSON.stringify(pendingQuestions));
            }
        }

        // Chat functionality
        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            const sendButton = document.getElementById('sendButton');
            
            if (!message) return;
            
            // Add user message to chat
            addMessageToChat('user', message);
            input.value = '';
            sendButton.disabled = true;
            
            // Show typing indicator
            showTypingIndicator();
            
            try {
                const response = await fetch('chat_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ message: message })
                });
                
                const data = await response.json();
                
                // Hide typing indicator
                hideTypingIndicator();
                
                if (data.reply) {
                    addMessageToChat('bot', data.reply);
                    
                    // If the response indicates forwarding to admin, store the question
                    if (data.reply.includes('forwarded your question to our admin') || 
                        data.reply.includes('already being reviewed')) {
                        storePendingQuestion(message);
                    }
                } else {
                    addMessageToChat('bot', 'Sorry, I encountered an error. Please try again.');
                }
                
            } catch (error) {
                console.error('Error:', error);
                hideTypingIndicator();
                addMessageToChat('bot', 'Sorry, I'm having connection issues. Please try again.');
            }
            
            sendButton.disabled = false;
            input.focus();
        });

        function addMessageToChat(sender, message) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            const avatar = sender === 'user' ? '👤' : sender === 'admin' ? '👨‍🔧' : '🤖';
            
            // Format message with service cards
            let formattedMessage = message;
            if (sender === 'bot') {
                formattedMessage = formatBotMessage(message);
            }
            
            messageDiv.innerHTML = `
                <div class="avatar">${avatar}</div>
                <div class="message-content">${formattedMessage}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function formatBotMessage(message) {
            // Convert markdown-style formatting
            let formatted = message
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Convert service listings to cards
            if (message.includes('• **') && message.includes('₱')) {
                const serviceBlocks = message.split('• **').slice(1);
                let cardsHTML = '';
                
                serviceBlocks.forEach(block => {
                    const lines = block.split('\n');
                    const name = lines[0].replace('**', '');
                    let desc = '', duration = '', price = '';
                    
                    lines.forEach(line => {
                        if (line.includes('📝')) desc = line.replace('📝', '').trim();
                        if (line.includes('🕒')) duration = line.replace('🕒 Duration:', '').trim();
                        if (line.includes('💰')) price = line.replace('💰 Price:', '').trim();
                    });
                    
                    cardsHTML += `
                        <div class="service-card">
                            <div class="service-name">${name}</div>
                            <div class="service-desc">${desc}</div>
                            <div class="service-meta">
                                <span>${duration}</span>
                                <span>${price}</span>
                            </div>
                        </div>
                    `;
                });
                
                formatted = cardsHTML;
            }
            
            return formatted.replace(/\n/g, '<br>');
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'flex';
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Start listening for admin replies
            setupSSEListener();
            
            // Focus on input
            document.getElementById('messageInput').focus();
            
            // Load any pending questions from localStorage
            const pendingQuestions = JSON.parse(localStorage.getItem('pendingQuestions') || '[]');
            if (pendingQuestions.length > 0) {
                console.log('Pending questions:', pendingQuestions);
            }
        });
    </script>
</body>
</html>
