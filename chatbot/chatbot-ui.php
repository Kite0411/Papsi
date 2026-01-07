<?php
// Admin Chatbot UI Component - FIXED VERSION
// Corrects API endpoint URLs and adds better error handling
?>
<style>
/* Same styling as before */
.chatbot-container{position:fixed;bottom:20px;right:20px;width:350px;max-width:calc(100vw - 40px);background:#fff;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,0.2);z-index:9999;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;transition:all .3s ease;overflow:hidden}
.chatbot-header{background:linear-gradient(135deg,#DC143C,#B71C1C);color:#fff;padding:15px 20px;border-radius:15px 15px 0 0;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.chatbot-header h3{margin:0;font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px}
.message-user .message-content{background:linear-gradient(135deg,#DC143C,#B71C1C);color:#fff;border-bottom-right-radius:4px}
.message-bot .message-content{background:#fff;color:#333;border:1px solid #e0e0e0;border-bottom-left-radius:4px;white-space:pre-line}
.message-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.message-user .message-avatar{background:#FFEBEE;color:#DC143C}
.message-bot .message-avatar{background:#f0f0f0;color:#666}
.chatbot-status{width:8px;height:8px;background:#4CAF50;border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%{opacity:1}50%{opacity:.5}100%{opacity:1}}
.chatbot-toggle{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;transition:transform .3s}
.chatbot-toggle:hover{transform:scale(1.1)}
.chatbot-body{height:400px;display:flex;flex-direction:column;transition:all .3s}
.chatbot-messages{flex:1;overflow-y:auto;padding:15px;background:#f8f9fa;max-height:300px}
.chatbot-message{margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;animation:fadeInUp .3s}
@keyframes fadeInUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.message-user{justify-content:flex-end}
.message-bot{justify-content:flex-start}
.message-content{max-width:80%;padding:10px 14px;border-radius:18px;font-size:14px;line-height:1.4;word-wrap:break-word}
.chatbot-input{padding:15px;background:#fff;border-top:1px solid #e0e0e0;display:flex;gap:10px;align-items:center}
.chatbot-input input{flex:1;border:1px solid #ddd;border-radius:20px;padding:10px 15px;font-size:14px;outline:none;transition:border-color .3s}
.chatbot-input input:focus{border-color:#DC143C;box-shadow:0 0 0 2px rgba(220,20,60,.1)}
.chatbot-send{background:linear-gradient(135deg,#DC143C,#B71C1C);color:#fff;border:none;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .3s;font-size:16px}
.chatbot-send:hover{transform:scale(1.05);box-shadow:0 4px 12px rgba(220,20,60,.3)}
.chatbot-send:disabled{opacity:.6;cursor:not-allowed;transform:none}
.chatbot-typing{display:none;padding:10px 15px;color:#666;font-style:italic;font-size:13px}
.chatbot-minimized{height:60px}
.chatbot-minimized .chatbot-body{display:none}
.chatbot-welcome{text-align:center;padding:20px;color:#666}
.chatbot-welcome h4{margin:0 0 10px 0;color:#333}
.chatbot-welcome p{margin:0 0 15px 0;font-size:13px}
.quick-questions{margin-top:15px;display:flex;flex-wrap:wrap;gap:8px}
.quick-question{background:#FFEBEE;color:#DC143C;border:1px solid #DC143C;border-radius:15px;padding:5px 12px;font-size:12px;cursor:pointer;transition:all .3s}
.quick-question:hover{background:#DC143C;color:#fff}
@media(max-width:768px){.chatbot-container{width:calc(100vw - 20px);right:10px;bottom:10px}.chatbot-body{height:350px}.chatbot-messages{max-height:250px}}
.typing-indicator{display:flex;gap:4px;padding:10px 15px}
.typing-dot{width:8px;height:8px;background:#ccc;border-radius:50%;animation:typing 1.4s infinite ease-in-out}
.typing-dot:nth-child(1){animation-delay:-.32s}
.typing-dot:nth-child(2){animation-delay:-.16s}
@keyframes typing{0%,80%,100%{transform:scale(.8);opacity:.5}40%{transform:scale(1);opacity:1}}
.pending-question-item{background:#fff3cd;border-left:3px solid #ffc107;padding:8px;margin:5px 0;font-size:12px;border-radius:4px}
.error-message{background:#f8d7da;border-left:3px solid #dc3545;padding:8px;margin:5px 0;font-size:12px;border-radius:4px;color:#721c24}
.success-message{background:#d4edda;border-left:3px solid #28a745;padding:8px;margin:5px 0;font-size:12px;border-radius:4px;color:#155724}
</style>

<div id="adminChatbot" class="chatbot-container" aria-live="polite">
    <div class="chatbot-header" onclick="toggleChatbot()" role="button" aria-expanded="true">
        <h3 style="color:white;">
            <span class="chatbot-status" id="chatbotStatus" title="Online"></span>
            🤖 Admin Panel
        </h3>
        <button class="chatbot-toggle" id="chatbotToggle" aria-label="Minimize chatbot">−</button>
    </div>
   
    <div class="chatbot-body" id="chatbotBody">
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="chatbot-welcome">
                <h4>🤖 Admin Assistant</h4>
                <p>Review customer questions and provide answers.</p>
                <div id="pendingList" style="text-align:left; margin-top:10px;"></div>
            </div>
        </div>
       
        <div class="chatbot-typing" id="chatbotTyping" aria-hidden="true">
            <div class="typing-indicator">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>
       
        <div class="chatbot-input">
            <input type="text" id="chatbotInput" placeholder="Enter your answer..." maxlength="500" aria-label="Chat input">
            <button class="chatbot-send" id="chatbotSend" onclick="sendChatbotMessage()" aria-label="Send message">➤</button>
        </div>
    </div>
</div>

<script>
// ==================== CONFIGURATION ====================
// 🔥 FIXED: Use correct Render API URL
const RENDER_BASE = 'https://papsi-chatbot-api-ouo1.onrender.com';
const API_URL = `${RENDER_BASE}/admin_chat`;
const POLL_URL = `${RENDER_BASE}/get_next_question`;
const PENDING_URL = `${RENDER_BASE}/pending`;

let currentQuestion = null;
let isFirstLoad = true;
let connectionStatus = 'checking';

// ==================== UI FUNCTIONS ====================

function addMessage(content, isUser = false) {
    const messages = document.getElementById('chatbotMessages');
    
    // Remove welcome message on first interaction
    const welcome = messages.querySelector('.chatbot-welcome');
    if (welcome && (isUser || !isFirstLoad)) {
        welcome.remove();
        isFirstLoad = false;
    }
    
    const div = document.createElement('div');
    div.className = `chatbot-message ${isUser ? 'message-user' : 'message-bot'}`;
    div.innerHTML = `
        <div class="message-avatar">${isUser ? '🧑‍🔧' : '🤖'}</div>
        <div class="message-content">${escapeHtml(content).replace(/\n/g, '<br>')}</div>
    `;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateConnectionStatus(status) {
    connectionStatus = status;
    const statusDot = document.getElementById('chatbotStatus');
    if (statusDot) {
        if (status === 'online') {
            statusDot.style.background = '#4CAF50';
            statusDot.title = 'Connected';
        } else if (status === 'offline') {
            statusDot.style.background = '#dc3545';
            statusDot.title = 'Disconnected';
        } else {
            statusDot.style.background = '#ffc107';
            statusDot.title = 'Connecting...';
        }
    }
}

// ==================== PENDING QUESTIONS ====================

async function loadAllPendingQuestions() {
    const pendingDiv = document.getElementById('pendingList');
    
    try {
        console.log('🔍 Fetching pending questions from:', PENDING_URL);
        
        const response = await fetch(PENDING_URL, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        console.log('📡 Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const questions = await response.json();
        
        console.log('📋 Received questions:', questions);
        
        // Handle both array and object responses
        let questionList = [];
        if (Array.isArray(questions)) {
            questionList = questions;
        } else if (questions.questions && Array.isArray(questions.questions)) {
            questionList = questions.questions;
        }
        
        if (questionList.length === 0) {
            pendingDiv.innerHTML = '<div class="success-message">✅ No pending questions</div>';
            updateConnectionStatus('online');
            return [];
        }
        
        // Display all pending questions
        let html = `<div style="margin-bottom:10px; color:#dc3545; font-weight:bold;">
                       📋 Pending Questions (${questionList.length})
                    </div>`;
        
        questionList.forEach((question, index) => {
            html += `<div class="pending-question-item">
                        <strong>${index + 1}.</strong> ${escapeHtml(question)}
                     </div>`;
        });
        
        pendingDiv.innerHTML = html;
        updateConnectionStatus('online');
        return questionList;
        
    } catch (error) {
        console.error('❌ Error loading pending questions:', error);
        pendingDiv.innerHTML = `<div class="error-message">⚠️ Cannot connect to server<br><small>${error.message}</small></div>`;
        updateConnectionStatus('offline');
        return [];
    }
}

async function autoLoadNextQuestion() {
    try {
        const response = await fetch(POLL_URL);
        const data = await response.json();
        
        if (data.new && data.question) {
            currentQuestion = data.question;
            
            // Only show if not already displayed
            const messages = document.getElementById('chatbotMessages');
            const messageText = messages.textContent || messages.innerText;
            
            if (!messageText.includes(data.question)) {
                addMessage(`📌 New Customer Question:\n\n"${data.question}"\n\nPlease provide your answer:`);
            }
            return true;
        }
        return false;
        
    } catch (error) {
        console.error('❌ Error loading next question:', error);
        return false;
    }
}

// ==================== SEND MESSAGE ====================

async function sendChatbotMessage() {
    const input = document.getElementById('chatbotInput');
    const sendBtn = document.getElementById('chatbotSend');
    const message = input.value.trim();
    
    if (!message) {
        addMessage("⚠️ Please type an answer first.");
        return;
    }
    
    // Show admin's message
    addMessage(message, true);
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: message })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        addMessage(data.reply || "✅ Answer saved!");
        
        // Refresh the pending list
        await loadAllPendingQuestions();
        await autoLoadNextQuestion();
        
        updateConnectionStatus('online');
        
    } catch (error) {
        console.error('❌ Admin chat error:', error);
        addMessage(`❌ Connection error: ${error.message}\n\nPlease check if the API is running.`);
        updateConnectionStatus('offline');
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
    }
}

// ==================== INITIALIZATION ====================

async function initializeAdminPanel() {
    updateConnectionStatus('checking');
    addMessage("👋 Initializing admin panel...");
    addMessage(`🔗 Connecting to: ${RENDER_BASE}`);
    
    // Test connection first
    try {
        const healthCheck = await fetch(`${RENDER_BASE}/health`, { method: 'GET' });
        
        if (healthCheck.ok) {
            addMessage("✅ Connected to server!");
            updateConnectionStatus('online');
        } else {
            addMessage("⚠️ Server responded but may have issues");
            updateConnectionStatus('offline');
        }
    } catch (error) {
        addMessage(`❌ Cannot connect to server: ${error.message}`);
        updateConnectionStatus('offline');
    }
    
    // Load pending questions
    const questions = await loadAllPendingQuestions();
    
    if (questions.length > 0) {
        addMessage(`📊 Found ${questions.length} pending question(s).`);
        await autoLoadNextQuestion();
    } else {
        addMessage("✅ No pending questions. Waiting for customer inquiries...");
    }
}

// ==================== EVENT LISTENERS ====================

document.getElementById('chatbotInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatbotMessage();
    }
});

// Auto-refresh every 10 seconds
setInterval(async () => {
    if (connectionStatus === 'online') {
        await loadAllPendingQuestions();
        await autoLoadNextQuestion();
    }
}, 10000);

// Initialize when page loads
document.addEventListener('DOMContentLoaded', initializeAdminPanel);

// ==================== TOGGLE CHATBOT ====================

let chatbotMinimized = false;

function toggleChatbot() {
    const chatbot = document.getElementById('adminChatbot');
    const toggle = document.getElementById('chatbotToggle');
    const body = document.getElementById('chatbotBody');
    
    chatbotMinimized = !chatbotMinimized;
    
    if (chatbotMinimized) {
        chatbot.classList.add('chatbot-minimized');
        toggle.textContent = '+';
        body.style.display = 'none';
    } else {
        chatbot.classList.remove('chatbot-minimized');
        toggle.textContent = '−';
        body.style.display = 'flex';
    }
}
</script>
