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
  Enhanced Customer Chatbot with Real-time Admin Answers
*/
const API_URL = '/chatbot/chat_proxy.php';
const STREAM_URL = 'https://papsi-chatbot-api.onrender.com/stream'; // SSE endpoint
let chatbotMinimized = true;
let isTyping = false;
let welcomeShown = false;
let eventSource = null;
let pendingQuestionsMap = new Map(); // Track pending questions and their timestamps

// ==================== CHAT UI FUNCTIONS ====================
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

function addMessage(content, isUser = false, isUpdate = false) {
    const messages = document.getElementById('chatbotMessages');
    const messageDiv = document.createElement('div');
    
    if (isUpdate) {
        // Special styling for admin updates
        messageDiv.className = 'chatbot-message message-update';
        messageDiv.innerHTML = `
            <div class="message-avatar">📢</div>
            <div class="message-content" style="background: #e8f5e8; border-left: 4px solid #4CAF50;">
                ${content}
            </div>
        `;
    } else {
        messageDiv.className = `chatbot-message ${isUser ? 'message-user' : 'message-bot'}`;
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.textContent = isUser ? '👤' : '🔧';
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
        
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(messageContent);
    }

    // Remove welcome message if it exists
    const welcome = document.getElementById('chatbotWelcome');
    if (welcome) welcome.remove();
   
    messages.appendChild(messageDiv);
    scrollToBottom();
    
    // Auto-expand chatbot if minimized and receiving update
    if (isUpdate && chatbotMinimized) {
        toggleChatbot();
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

// ==================== ENHANCED MESSAGE SENDING ====================
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
            
            // 🆕 Track if this question was forwarded to admin
            if (data.reply.includes("forwarded your question") || 
                data.reply.includes("I'm not sure") ||
                data.reply.includes("I'll ask our")) {
                trackPendingQuestion(message);
            }
        } else {
            addMessage('Sorry, I encountered an unexpected response from the server.', false);
        }
       
    } catch (err) {
        hideTyping();
        if (err.name === 'AbortError') {
            addMessage('Sorry, the request timed out. Please try again.', false);
        } else {
            addMessage('Sorry, I\'m having trouble connecting. Please try again.', false);
        }
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
    }
}

// ==================== REAL-TIME ADMIN ANSWER SYSTEM ====================
function initializeSSE() {
    try {
        eventSource = new EventSource(STREAM_URL);
        
        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                if (data.message) {
                    // 🆕 Enhanced notification with better formatting
                    const formattedMessage = data.message
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\n/g, '<br>');
                    
                    addMessage(formattedMessage, false, true);
                    
                    // Remove from pending tracking if this answers one of our questions
                    removeFromPendingTracking(data.message);
                }
            } catch (e) {
                console.log('SSE message parse error:', e);
            }
        };
        
        eventSource.onerror = function(err) {
            console.log('SSE connection error, attempting reconnect...', err);
            // Auto-reconnect after 5 seconds
            setTimeout(() => {
                if (eventSource) eventSource.close();
                initializeSSE();
            }, 5000);
        };
        
        console.log('✅ Real-time updates enabled');
    } catch (err) {
        console.log('❌ SSE not supported, falling back to polling');
        startPollingFallback();
    }
}

// ==================== PENDING QUESTION TRACKING ====================
function trackPendingQuestion(question) {
    const timestamp = Date.now();
    pendingQuestionsMap.set(question, timestamp);
    
    // Save to localStorage for persistence
    savePendingQuestionsToStorage();
    
    console.log(`📝 Tracking pending question: "${question}"`);
}

function removeFromPendingTracking(message) {
    // Check if this message answers any of our pending questions
    for (const [question] of pendingQuestionsMap) {
        if (message.includes(question)) {
            pendingQuestionsMap.delete(question);
            savePendingQuestionsToStorage();
            console.log(`✅ Removed answered question: "${question}"`);
            break;
        }
    }
}

function savePendingQuestionsToStorage() {
    const questionsArray = Array.from(pendingQuestionsMap.entries());
    localStorage.setItem('customer_pending_questions', JSON.stringify(questionsArray));
}

function loadPendingQuestionsFromStorage() {
    try {
        const stored = localStorage.getItem('customer_pending_questions');
        if (stored) {
            const questionsArray = JSON.parse(stored);
            pendingQuestionsMap = new Map(questionsArray);
        }
    } catch (e) {
        console.log('Error loading pending questions:', e);
    }
}

// ==================== POLLING FALLBACK ====================
function startPollingFallback() {
    // Poll every 30 seconds for updates
    setInterval(async () => {
        if (pendingQuestionsMap.size === 0) return;
        
        for (const [question] of pendingQuestionsMap) {
            try {
                const resp = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: question })
                });
                
                if (resp.ok) {
                    const data = await resp.json();
                    // Check if this question now has a proper answer (not the "forwarded" response)
                    if (data.reply && 
                        !data.reply.includes("forwarded your question") &&
                        !data.reply.includes("I'm not sure") &&
                        !data.reply.includes("I'll ask our")) {
                        
                        // Show the update
                        addMessage(
                            `✅ <strong>Update on your question:</strong> "${question}"<br><br>` +
                            `<strong>Admin Response:</strong> ${data.reply}`, 
                            false, 
                            true
                        );
                        
                        // Remove from tracking
                        pendingQuestionsMap.delete(question);
                        savePendingQuestionsToStorage();
                    }
                }
            } catch (err) {
                console.log('Polling error:', err);
            }
        }
        
        // Clean up old questions (older than 24 hours)
        const now = Date.now();
        const dayInMs = 24 * 60 * 60 * 1000;
        for (const [question, timestamp] of pendingQuestionsMap) {
            if (now - timestamp > dayInMs) {
                pendingQuestionsMap.delete(question);
            }
        }
        savePendingQuestionsToStorage();
        
    }, 30000); // Every 30 seconds
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
    // Load any pending questions from previous sessions
    loadPendingQuestionsFromStorage();
    
    // Initialize real-time updates
    initializeSSE();
    
    // Show welcome message
    setTimeout(() => {
        const messages = document.getElementById('chatbotMessages');
        if (messages.children.length === 1) {
            addMessage("Hi! I'm your vehicle diagnostic assistant. Describe your car problem and I'll recommend the right service! 🔧", false);
        }
    }, 1000);
});

// Add CSS for update messages
const updateStyle = document.createElement('style');
updateStyle.textContent = `
/* Add to existing styles */
.message-update {
    justify-content: flex-start;
    animation: pulse-gentle 2s ease-in-out;
}

.message-update .message-content {
    background: #e8f5e8 !important;
    border-left: 4px solid #4CAF50 !important;
    border-radius: 12px;
    padding: 12px 16px;
    max-width: 90%;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.15);
}

@keyframes pulse-gentle {
    0% { 
        opacity: 0.7; 
        transform: translateY(10px); 
    }
    100% { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

/* Notification badge for minimized chatbot */
.chatbot-icon::after {
    content: '';
    position: absolute;
    top: -5px;
    right: -5px;
    width: 12px;
    height: 12px;
    background: #ff4444;
    border-radius: 50%;
    border: 2px solid #fff;
    display: none;
}

.chatbot-icon.has-notification::after {
    display: block;
}
`;
document.head.appendChild(updateStyle);
</script>
