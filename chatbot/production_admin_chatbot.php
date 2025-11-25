// 🔥 FIXED: Now uses the SAME backend as customer bot
const RENDER_URL = 'https://papsi-chatbot-api.onrender.com';
// Use the unified bot endpoints (production_bot.py)
const API_URL = `${RENDER_URL}/admin_chat`;
const POLL_URL = `${RENDER_URL}/get_next_question`;
const PENDING_URL = `${RENDER_URL}/pending`;
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
function addMessage(content, isUser = false) {
    const messages = document.getElementById('chatbotMessages');
    const div = document.createElement('div');
    div.className = `chatbot-message ${isUser ? 'message-user' : 'message-bot'}`;
    div.innerHTML = `
        <div class="message-avatar">${isUser ? '🧑‍🔧' : '🤖'}</div>
        <div class="message-content">${content}</div>
    `;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}
async function sendChatbotMessage() {
    const input = document.getElementById('chatbotInput');
    const message = input.value.trim();
    if (!message) return;
    addMessage(message, true);
    input.value = '';
    try {
        const resp = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message })
        });
        const data = await resp.json();
        addMessage(data.reply || "✅ Answer saved!");
       
        // Refresh pending list after saving
        await loadPendingQuestions();
    } catch (e) {
        console.error('Admin chat error:', e);
        addMessage("❌ Connection error. Please check if the bot is running.");
    }
}
// Allow pressing Enter to send
document.getElementById('chatbotInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatbotMessage();
    }
});
// Load pending questions on startup
async function loadPendingQuestions() {
    try {
        const resp = await fetch(PENDING_URL);
        const questions = await resp.json();
       
        const pendingDiv = document.getElementById('pendingList');
        if (questions.length > 0) {
            pendingDiv.innerHTML = `<strong>📋 Pending Questions (${questions.length}):</strong>`;
            questions.forEach((q, i) => {
                pendingDiv.innerHTML += `<div style="padding:5px;margin:5px 0;background:#fff3cd;border-left:3px solid #ffc107;font-size:12px;">${i+1}. ${q}</div>`;
            });
        } else {
            pendingDiv.innerHTML = '<div style="color:#28a745;font-size:13px;">✅ No pending questions</div>';
        }
    } catch (e) {
        console.error('Failed to load pending questions:', e);
        document.getElementById('pendingList').innerHTML = '<div style="color:#dc3545;font-size:13px;">⚠️ Error loading pending questions</div>';
    }
}
// 🧠 Start conversation automatically
document.addEventListener('DOMContentLoaded', async () => {
    addMessage("👋 Hello admin! Connecting to Render API...");
    addMessage(`🔗 Backend: ${RENDER_URL}`);
   
    await loadPendingQuestions();
    try {
        const resp = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message: 'start' })
        });
        const data = await resp.json();
        addMessage(data.reply);
    } catch (e) {
        console.error('Connection error:', e);
        addMessage(`❌ Could not connect to bot at ${RENDER_URL}`);
    }
    // 🔄 Poll the server every 5 seconds for new questions
    setInterval(async () => {
        try {
            const res = await fetch(POLL_URL);
            const data = await res.json();
            if (data.new) {
                addMessage(`🆕 New question from customer:\n❓ ${data.question}`);
                await loadPendingQuestions(); // Refresh pending list
            }
        } catch (err) {
            console.log("Polling error:", err);
        }
    }, 5000);
});
