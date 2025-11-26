Chatbot-ui
<?php
// Chatbot UI Component - Vehicle Diagnostic Assistant
?>
<style>
/* (styles unchanged from your original, kept compact here) */
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
.chatbot-minimized {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #DC143C, #B71C1C);
  box-shadow: 0 6px 20px rgba(220, 20, 60, 0.4);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 26px;
  transition: all .3s ease;
}
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
</style>
<div id="chatbot" class="chatbot-container" aria-live="polite">
    <div class="chatbot-icon" id="chatbotIcon" style="
    display:none;
    position:fixed;
    bottom:20px;
    right:20px;
    width:60px;
    height:60px;
    border-radius:50%;
    background:linear-gradient(135deg,#DC143C,#B71C1C);
    box-shadow:0 6px 20px rgba(220,20,60,0.4);
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:28px;
    cursor:pointer;
    z-index:9999;
    " onclick="toggleChatbot()">💬</div>
    <div class="chatbot-header" onclick="toggleChatbot()" role="button" aria-expanded="true">
        <h3>
            <span class="chatbot-status" id="chatbotStatus" title="Online"></span>
           
        </h3>
        <button class="chatbot-toggle" id="chatbotToggle" aria-label="Minimize chatbot">-</button>
    </div>
   
    <div class="chatbot-body" id="chatbotBody">
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="chatbot-welcome" id="chatbotWelcome">
                <h4>🔧 Vehicle Diagnostic Assistant</h4>
                <p>Describe your vehicle problem and I'll recommend the right service for you!</p>
                <div class="quick-questions">
                    <div class="quick-question" onclick="askQuestion('My engine is making a strange noise')">Engine Noise</div>
                    <div class="quick-question" onclick="askQuestion('My brakes are squeaking')">Brake Issues</div>
                    <div class="quick-question" onclick="askQuestion('My AC is not cooling')">AC Problems</div>
                <div class="quick-question" onclick="askQuestion('My car won\\'t start')">Starting Issues</div>
                </div>
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
            <input type="text" id="chatbotInput" placeholder="Describe your vehicle problem..." maxlength="500" aria-label="Chat input">
            <button class="chatbot-send" id="chatbotSend" onclick="sendChatbotMessage()" aria-label="Send message">➤</button>
        </div>
    </div>
</div>
<script>
/*
  Frontend adapted for Flask backend at:
  http://127.0.0.1:5000/chat
  - Uses POST JSON { message }
  - Expects JSON { reply }
  - Includes timeout and better error handling
*/
const API_URL = '/chatbot/chat_proxy.php';
const SSE_URL = '/stream';

let chatbotMinimized = true;
let isTyping = false;
let welcomeShown = false;
let eventSource = null;
let pendingQuestions = [];

// ==================== SSE REAL-TIME UPDATES ====================
function setupSSE() {
    try {
        eventSource = new EventSource(SSE_URL);
        
        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                const notification = JSON.parse(data.message);
                
                console.log('SSE received:', notification);
                
                if (notification.type === 'admin_reply') {
                    showAdminReply(notification);
                    showNotification('🎉 Admin has replied to your question!');
                    removePendingQuestion(notification.original_question);
                }
            } catch (e) {
                console.log('SSE raw message:', event.data);
            }
        };
        
        eventSource.onerror = function(event) {
            console.error('SSE connection error:', event);
            setTimeout(setupSSE, 5000);
        };
        
        console.log('SSE connection established');
    } catch (error) {
        console.error('Failed to setup SSE:', error);
    }
}

function showAdminReply(notification) {
    const messages = document.getElementById('chatbotMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chatbot-message message-admin';
    
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.textContent = '👨‍🔧';
    
    const messageContent = document.createElement('div');
    messageContent.className = 'message-content';
    messageContent.innerHTML = `
        <strong>Admin Response:</strong><br>
        ${escapeHtml(notification.admin_answer).replace(/\n/g, '<br>')}
    `;
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(messageContent);
    messages.appendChild(messageDiv);
    
    scrollToBottom();
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        max-width: 300px;
        font-size: 14px;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => notification.style.transform = 'translateX(0)', 100);

    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// ==================== PENDING QUESTIONS TRACKING ====================
function loadPendingQuestions() {
    const stored = localStorage.getItem('chatbot_pending_questions');
    if (stored) {
        try {
            pendingQuestions = JSON.parse(stored);
        } catch (e) {
            pendingQuestions = [];
        }
    }
    return pendingQuestions;
}

function savePendingQuestions() {
    localStorage.setItem('chatbot_pending_questions', JSON.stringify(pendingQuestions));
}

function addPendingQuestion(question) {
    loadPendingQuestions();
    if (!pendingQuestions.find(q => q.question === question)) {
        pendingQuestions.push({
            question: question,
            timestamp: Date.now()
        });
        savePendingQuestions();
        console.log('Question added to pending:', question);
    }
}

function removePendingQuestion(question) {
    loadPendingQuestions();
    const initialLength = pendingQuestions.length;
    pendingQuestions = pendingQuestions.filter(q => q.question !== question);
    if (pendingQuestions.length !== initialLength) {
        savePendingQuestions();
        console.log('Question removed from pending:', question);
    }
}

// ==================== CHAT FUNCTIONS ====================
function toggleChatbot() {
    const chatbot = document.getElementById('chatbot');
    const toggle = document.getElementById('chatbotToggle');
    const body = document.getElementById('chatbotBody');
    const status = document.getElementById('chatbotStatus');
    const icon = document.getElementById('chatbotIcon');
    chatbotMinimized = !chatbotMinimized;
   
    if (chatbotMinimized) {
        chatbot.classList.add('chatbot-minimized');
        toggle.textContent = '';
        body.style.display = 'none';
        icon.style.display = 'flex';
        status.style.display = 'none';
        toggle.setAttribute('aria-expanded', 'false');
    } else {
        chatbot.classList.remove('chatbot-minimized');
        toggle.textContent = '−';
        body.style.display = 'flex';
        icon.style.display = 'none';
        status.style.display = 'inline-block';
        toggle.setAttribute('aria-expanded', 'true');
        document.getElementById('chatbotInput').focus();
    }
}

function showTyping() {
    if (isTyping) return;
    isTyping = true;
    const typing = document.getElementById('chatbotTyping');
    typing.style.display = 'block';
    typing.setAttribute('aria-hidden', 'false');
    scrollToBottom();
}

function hideTyping() {
    isTyping = false;
    const typing = document.getElementById('chatbotTyping');
    typing.style.display = 'none';
    typing.setAttribute('aria-hidden', 'true');
}

function addMessage(content, isUser = false) {
    const messages = document.getElementById('chatbotMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chatbot-message ${isUser ? 'message-user' : 'message-bot'}`;
   
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.textContent = isUser ? '👤' : '🔧';
   
    const messageContent = document.createElement('div');
    messageContent.className = 'message-content';
    messageContent.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
   
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(messageContent);
   
    const welcome = document.getElementById('chatbotWelcome');
    if (welcome) welcome.remove();
   
    messages.appendChild(messageDiv);
    scrollToBottom();
    
    // Track pending questions when bot says it's forwarding to admin
    if (!isUser && content && (
        content.includes("I'm not sure") ||
        content.includes("forwarded your question") ||
        content.includes("already being reviewed")
    )) {
        const userMessages = messages.querySelectorAll('.message-user .message-content');
        if (userMessages.length > 0) {
            const lastUserMessage = userMessages[userMessages.length - 1].textContent;
            addPendingQuestion(lastUserMessage);
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

function scrollToBottom() {
    const messages = document.getElementById('chatbotMessages');
    setTimeout(() => messages.scrollTop = messages.scrollHeight, 80);
}

function askQuestion(question) {
    const input = document.getElementById('chatbotInput');
    input.value = question;
    sendChatbotMessage();
}

async function sendChatbotMessage() {
    const input = document.getElementById('chatbotInput');
    const sendBtn = document.getElementById('chatbotSend');
    const message = input.value.trim();
   
    if (!message || isTyping) return;
   
    input.disabled = true;
    sendBtn.disabled = true;
   
    addMessage(message, true);
    input.value = '';
   
    showTyping();
   
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
   
    try {
        const resp = await fetch(API_URL, {
            method: 'POST',
            mode: 'cors',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message }),
            signal: controller.signal
        });
       
        clearTimeout(timeout);
       
        if (!resp.ok) {
            let text = await resp.text().catch(()=>null);
            hideTyping();
            addMessage(`Sorry, server returned ${resp.status}. ${text ? 'Details: '+text : ''}`, false);
            return;
        }
       
        const data = await resp.json().catch(() => null);
        hideTyping();
       
        if (data && data.reply) {
            addMessage(data.reply, false);
        } else {
            addMessage('Sorry, I encountered an unexpected response from the server.', false);
            console.error('Unexpected server response:', data);
        }
       
    } catch (err) {
        hideTyping();
        if (err.name === 'AbortError') {
            addMessage('Sorry, the request timed out. The model may be busy or the server is not responding.', false);
        } else {
            addMessage('Sorry, I\'m having trouble connecting to the chatbot server. Please try again in a moment', false);
            console.error('Chatbot error:', err);
        }
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
    }
}

// ==================== EVENT LISTENERS ====================
document.getElementById('chatbotInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatbotMessage();
    }
});

document.getElementById('chatbotInput').addEventListener('focus', function() {
    if (chatbotMinimized) toggleChatbot();
});

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    // Setup real-time SSE connection
    setupSSE();
    
    // Load any existing pending questions
    loadPendingQuestions();
    
    // Initial welcome message
    setTimeout(() => {
        const messages = document.getElementById('chatbotMessages');
        if (messages.children.length === 1 && !welcomeShown) {
            addMessage("Hi! I'm your vehicle diagnostic assistant. Describe your car problem and I'll recommend the right service for you!", false);
            welcomeShown = true;
        }
    }, 600);
});

// Clean up SSE connection when page unloads
window.addEventListener('beforeunload', function() {
    if (eventSource) {
        eventSource.close();
    }
});
