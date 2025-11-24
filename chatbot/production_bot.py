"""
Papsi Repair Shop - Unified Chatbot API
Combines customer and admin chatbot functionality
Optimized for Render deployment
"""

from flask import Flask, request, jsonify, Response
from flask_cors import CORS
import pandas as pd
from sentence_transformers import SentenceTransformer, util
import os
import mysql.connector
from queue import Queue
import requests

app = Flask(__name__)

# Configure CORS for Hostinger frontend
CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "*").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type"]
    }
})

# ==================== CONFIGURATION ====================

# MySQL Database Configuration (Hostinger Remote)
DB_CONFIG = {
    'host': os.environ.get('DB_HOST', '127.0.0.1'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'autorepair_db'),
    'port': int(os.environ.get('DB_PORT', 3306))
}

# Files
FAQ_FILE = os.path.join(os.path.dirname(__file__), 'faq.csv')
PENDING_FILE = os.path.join(os.path.dirname(__file__), 'pending_questions.csv')

# Initialize CSV files
if not os.path.exists(FAQ_FILE):
    pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
if not os.path.exists(PENDING_FILE):
    pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)

# ==================== AI MODEL ====================
# Load sentence transformer model for semantic search
print("🤖 Loading AI model...")
try:
    model = SentenceTransformer('all-MiniLM-L6-v2')
    print("✅ AI model loaded successfully")
except Exception as e:
    print(f"⚠️ Error loading AI model: {e}")
    model = None

# ==================== SSE CLIENTS ====================
# Server-Sent Events for real-time notifications
clients = []

def send_sse_message(data):
    """Send message to all connected SSE clients"""
    for q in clients[:]:
        try:
            q.put(data, timeout=0.2)
        except Exception:
            clients.remove(q)

# ==================== DATABASE FUNCTIONS ====================

def get_db_connection():
    """Connect to MySQL database (Hostinger)"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as e:
        print(f"⚠️ Database connection error: {e}")
        return None

def get_services_from_db():
    """Fetch services from database"""
    try:
        conn = get_db_connection()
        if not conn:
            return []

        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT service_name, description, duration, price FROM services WHERE is_archived = 0")
        services = cursor.fetchall()
        cursor.close()
        conn.close()
        return services
    except Exception as e:
        print(f"⚠️ Database error: {e}")
        return []

# ==================== CUSTOMER CHATBOT ENDPOINTS ====================

@app.route('/chat', methods=['POST'])
def chat():
    """
    Main customer chatbot endpoint
    Uses AI semantic search to match questions and recommend services
    """
    data = request.get_json()
    user_message = data.get('message', '').strip()

    if not user_message:
        return jsonify({'reply': "Please describe your vehicle problem so I can assist you."})

    # ---------- 1️⃣ Check FAQ (semantic search) ----------
    faq_data = pd.read_csv(FAQ_FILE)
    faq_reply = None
    similarity_score = 0

    if len(faq_data) > 0 and model:
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()

        try:
            faq_embeddings = model.encode(questions, convert_to_tensor=True)
            user_emb = model.encode(user_message, convert_to_tensor=True)
            similarities = util.cos_sim(user_emb, faq_embeddings)
            index = int(similarities.argmax())
            similarity_score = float(similarities[0][index])

            if similarity_score >= 0.4:
                faq_reply = answers[index]
        except Exception as e:
            print(f"⚠️ Error in FAQ search: {e}")

    # ---------- 2️⃣ Check Services (semantic search) ----------
    services = get_services_from_db()
    top_services = []

    if services and model:
        try:
            text_data = [f"{s['service_name']} {s['description']}" for s in services]
            service_embeddings = model.encode(text_data, convert_to_tensor=True)
            user_emb = model.encode(user_message, convert_to_tensor=True)
            similarities = util.cos_sim(user_emb, service_embeddings)[0]

            scores = similarities.cpu().tolist()
            ranked = sorted(enumerate(scores), key=lambda x: x[1], reverse=True)

            for idx, score in ranked[:3]:
                if score > 0.25:
                    top_services.append((services[idx], score))
        except Exception as e:
            print(f"⚠️ Error in service search: {e}")

    # ---------- 3️⃣ Build reply ----------
    reply_parts = []

    if faq_reply:
        reply_parts.append(f"🔧 {faq_reply}")

    if top_services:
        reply_parts.append("🧰 Based on your concern, here are some services you might need:")
        for s, score in top_services:
            part = (
                f"• **{s['service_name']}**\n"
                f"📝 {s['description']}\n"
                f"🕒 Duration: {s['duration']}\n"
                f"💰 Price: ₱{float(s['price']):,.2f}"
            )
            reply_parts.append(part)

    if not faq_reply and not top_services:
        # ---------- 4️⃣ Forward to Admin ----------
        pending = pd.read_csv(PENDING_FILE)
        if user_message not in pending['question'].values:
            new_row = pd.DataFrame({'question': [user_message]})
            pending = pd.concat([pending, new_row], ignore_index=True)
            pending.to_csv(PENDING_FILE, index=False)

        reply_parts.append(
            "🤖 I'm not sure about that yet. I've forwarded your question to our admin for review. "
            "You'll be updated here once the admin provides an answer."
        )

    reply = "\n\n".join(reply_parts)
    return jsonify({'reply': reply})


@app.route('/notify_customer', methods=['POST'])
def notify_customer():
    """
    Receives answer from admin and notifies customers
    """
    data = request.get_json()
    question = data.get("question")
    answer = data.get("answer")

    if not question or not answer:
        return jsonify({"reply": "Invalid data"}), 400

    # Save to FAQ
    faq = pd.read_csv(FAQ_FILE)
    new_entry = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_entry], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)

    print(f"📩 Admin answered: {question} -> {answer}")

    # Send SSE notification to connected clients
    send_sse_message(f"✅ Update from admin: {answer}")

    return jsonify({"reply": "Customer notified."})


@app.route('/stream')
def stream():
    """
    Server-Sent Events endpoint for real-time notifications
    """
    q = Queue()
    clients.append(q)

    def event_stream(queue):
        while True:
            msg = queue.get()
            yield f"data: {msg}\n\n"

    return Response(event_stream(q), mimetype="text/event-stream")

# ==================== ADMIN CHATBOT ENDPOINTS ====================

# Global state for admin conversation
current_question = None


@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """
    Check if there's a new question waiting for admin
    """
    global current_question

    pending = pd.read_csv(PENDING_FILE)
    if pending.empty:
        return jsonify({'new': False})

    if current_question is None:
        current_question = pending.iloc[0]['question']
        return jsonify({'new': True, 'question': current_question})

    return jsonify({'new': False})


@app.route('/admin_chat', methods=['POST'])
def admin_chat():
    """
    Admin chatbot endpoint - handles conversational Q&A workflow
    """
    global current_question
    data = request.get_json()
    message = data.get('message', '').strip()

    if not message:
        return jsonify({'reply': 'Please type something.'})

    # Start a conversation if none is active
    if current_question is None:
        pending = pd.read_csv(PENDING_FILE)
        if pending.empty:
            return jsonify({'reply': '✅ No pending questions right now.'})
        current_question = pending.iloc[0]['question']
        return jsonify({'reply': f"❓ {current_question}\n\nPlease provide your answer:"})

    # Save answer to FAQ
    question = current_question
    answer = message

    faq = pd.read_csv(FAQ_FILE)

    # Remove duplicates + remove same question
    faq = faq.drop_duplicates(subset=['question'], keep='last')
    faq = faq[faq['question'] != question]

    # Insert new entry
    new_row = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_row], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)

    # Remove from pending
    pending = pd.read_csv(PENDING_FILE)
    pending = pending[pending['question'] != question]
    pending.to_csv(PENDING_FILE, index=False)

    print(f"✅ Saved to FAQ: {question} -> {answer}")

    # Notify customers via SSE
    send_sse_message(f"✅ Update from admin: {answer}")

    # Reset and check for next question
    current_question = None
    pending = pd.read_csv(PENDING_FILE)

    if not pending.empty:
        next_q = pending.iloc[0]['question']
        current_question = next_q
        reply = f"✅ Answer saved and sent!\n\nNext question:\n❓ {next_q}\nPlease provide your answer:"
    else:
        reply = "✅ Answer saved and customer notified! No more pending questions."

    return jsonify({'reply': reply})


@app.route('/pending', methods=['GET'])
def get_pending():
    """
    Get all pending questions (for admin dashboard)
    """
    try:
        pending = pd.read_csv(PENDING_FILE)
        questions = pending['question'].tolist()
        return jsonify(questions)
    except Exception as e:
        print(f"⚠️ Error fetching pending questions: {e}")
        return jsonify([])

# ==================== UTILITY ENDPOINTS ====================

@app.route('/health', methods=['GET'])
def health():
    """
    Health check endpoint for Render monitoring
    """
    status = {
        "status": "healthy",
        "service": "papsi-chatbot-api",
        "model_loaded": model is not None,
        "database": "connected" if get_db_connection() else "disconnected"
    }
    return jsonify(status), 200


@app.route('/', methods=['GET'])
def root():
    """
    API documentation endpoint
    """
    return jsonify({
        "service": "Papsi Repair Shop - Unified Chatbot API",
        "version": "2.0.0",
        "endpoints": {
            "customer": {
                "/chat": "POST - Main chatbot endpoint",
                "/stream": "GET - Real-time notifications (SSE)"
            },
            "admin": {
                "/admin_chat": "POST - Admin Q&A interface",
                "/get_next_question": "GET - Check for pending questions",
                "/pending": "GET - Get all pending questions",
                "/notify_customer": "POST - Send answer to customers"
            },
            "utility": {
                "/health": "GET - Health check",
                "/": "GET - API documentation"
            }
        }
    })


# ==================== APPLICATION ENTRY POINT ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    debug = os.environ.get("FLASK_ENV", "development") == "development"

    print(f"""
    ╔════════════════════════════════════════════╗
    ║   Papsi Chatbot API - Production Server   ║
    ╠════════════════════════════════════════════╣
    ║   Port: {port}                                ║
    ║   Debug: {debug}                              ║
    ║   AI Model: {'Loaded' if model else 'Failed'}                       ║
    ╚════════════════════════════════════════════╝
    """)

    app.run(host="0.0.0.0", port=port, debug=debug, threaded=True)
