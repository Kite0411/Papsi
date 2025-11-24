"""
Papsi Repair Shop - Unified Chatbot API
OPTIMIZED FOR RENDER FREE TIER - No Timeouts!
"""

from flask import Flask, request, jsonify, Response
from flask_cors import CORS
import pandas as pd
import os
import mysql.connector
from queue import Queue
import threading

app = Flask(__name__)

# Configure CORS
CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "*").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type"]
    }
})

# ==================== CONFIGURATION ====================

# MySQL Database Configuration
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

# ==================== AI MODEL - LAZY LOADING ====================
# Model loads IN BACKGROUND to avoid blocking startup

model = None
model_loading = False
model_loaded = False

def load_model_background():
    """Load model in background thread to avoid blocking"""
    global model, model_loading, model_loaded
    
    if model_loaded or model_loading:
        return
    
    model_loading = True
    print("🤖 Loading AI model in background...")
    
    try:
        from sentence_transformers import SentenceTransformer
        model = SentenceTransformer('all-MiniLM-L6-v2')
        model_loaded = True
        print("✅ AI model loaded successfully")
    except Exception as e:
        print(f"⚠️ Error loading AI model: {e}")
        model_loaded = False
    finally:
        model_loading = False

# Start loading model immediately but don't block
threading.Thread(target=load_model_background, daemon=True).start()

# ==================== SSE CLIENTS ====================
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
    """Connect to MySQL database"""
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

# ==================== SEMANTIC SEARCH FUNCTIONS ====================

def semantic_search_faq(user_message, faq_data):
    """Search FAQ using semantic similarity"""
    if not model_loaded or model is None or len(faq_data) == 0:
        return None, 0
    
    try:
        from sentence_transformers import util
        
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        
        faq_embeddings = model.encode(questions, convert_to_tensor=True)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        similarities = util.cos_sim(user_emb, faq_embeddings)
        
        index = int(similarities.argmax())
        similarity_score = float(similarities[0][index])
        
        if similarity_score >= 0.4:
            return answers[index], similarity_score
        
        return None, similarity_score
    except Exception as e:
        print(f"⚠️ Error in FAQ search: {e}")
        return None, 0

def semantic_search_services(user_message, services):
    """Search services using semantic similarity"""
    if not model_loaded or model is None or not services:
        return []
    
    try:
        from sentence_transformers import util
        
        text_data = [f"{s['service_name']} {s['description']}" for s in services]
        service_embeddings = model.encode(text_data, convert_to_tensor=True)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        similarities = util.cos_sim(user_emb, service_embeddings)[0]
        
        scores = similarities.cpu().tolist()
        ranked = sorted(enumerate(scores), key=lambda x: x[1], reverse=True)
        
        top_services = []
        for idx, score in ranked[:3]:
            if score > 0.25:
                top_services.append((services[idx], score))
        
        return top_services
    except Exception as e:
        print(f"⚠️ Error in service search: {e}")
        return []

# ==================== FALLBACK: KEYWORD MATCHING ====================

def keyword_search_services(user_message, services):
    """Fallback: Simple keyword matching when AI model not ready"""
    keywords = user_message.lower().split()
    matches = []
    
    for service in services:
        name = service['service_name'].lower()
        desc = service['description'].lower()
        
        score = sum(1 for kw in keywords if kw in name or kw in desc)
        if score > 0:
            matches.append((service, score))
    
    # Sort by score and return top 3
    matches.sort(key=lambda x: x[1], reverse=True)
    return matches[:3]

# ==================== HEALTH CHECK (INSTANT RESPONSE) ====================

@app.route('/health', methods=['GET'])
def health():
    """Health check - responds INSTANTLY"""
    return jsonify({
        "status": "healthy",
        "model_status": "loaded" if model_loaded else ("loading" if model_loading else "not_loaded")
    }), 200

@app.route('/', methods=['GET'])
def root():
    """Root endpoint"""
    return jsonify({
        "service": "Papsi Repair Shop Chatbot API",
        "status": "running",
        "model_ready": model_loaded
    }), 200

# ==================== CUSTOMER CHATBOT ====================

@app.route('/chat', methods=['POST'])
def chat():
    """Main customer chatbot - works even while model loads"""
    data = request.get_json()
    user_message = data.get('message', '').strip()

    if not user_message:
        return jsonify({'reply': "Please describe your vehicle problem so I can assist you."})

    reply_parts = []
    
    # ---------- 1️⃣ Check FAQ ----------
    faq_data = pd.read_csv(FAQ_FILE)
    faq_reply = None
    
    if model_loaded:
        faq_reply, score = semantic_search_faq(user_message, faq_data)
    else:
        # Fallback: exact or partial string matching
        if len(faq_data) > 0:
            for idx, row in faq_data.iterrows():
                if user_message.lower() in row['question'].lower():
                    faq_reply = row['answer']
                    break

    # ---------- 2️⃣ Check Services ----------
    services = get_services_from_db()
    top_services = []
    
    if model_loaded:
        top_services = semantic_search_services(user_message, services)
    else:
        # Fallback: keyword matching
        top_services = keyword_search_services(user_message, services)

    # ---------- 3️⃣ Build Reply ----------
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
        # Forward to Admin
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

# ==================== ADMIN ENDPOINTS ====================

current_question = None

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """Check for new pending questions"""
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
    """Admin chatbot endpoint"""
    global current_question
    data = request.get_json()
    message = data.get('message', '').strip()

    if not message:
        return jsonify({'reply': 'Please type something.'})

    if current_question is None:
        pending = pd.read_csv(PENDING_FILE)
        if pending.empty:
            return jsonify({'reply': '✅ No pending questions right now.'})
        current_question = pending.iloc[0]['question']
        return jsonify({'reply': f"❓ {current_question}\n\nPlease provide your answer:"})

    # Save answer
    question = current_question
    answer = message

    faq = pd.read_csv(FAQ_FILE)
    faq = faq.drop_duplicates(subset=['question'], keep='last')
    faq = faq[faq['question'] != question]
    new_row = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_row], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)

    # Remove from pending
    pending = pd.read_csv(PENDING_FILE)
    pending = pending[pending['question'] != question]
    pending.to_csv(PENDING_FILE, index=False)

    print(f"✅ Saved to FAQ: {question} -> {answer}")
    send_sse_message(f"✅ Update from admin: {answer}")

    # Check for next question
    current_question = None
    pending = pd.read_csv(PENDING_FILE)

    if not pending.empty:
        next_q = pending.iloc[0]['question']
        current_question = next_q
        reply = f"✅ Answer saved!\n\nNext question:\n❓ {next_q}\nPlease provide your answer:"
    else:
        reply = "✅ Answer saved and customer notified! No more pending questions."

    return jsonify({'reply': reply})

@app.route('/notify_customer', methods=['POST'])
def notify_customer():
    """Notify customers of admin response"""
    data = request.get_json()
    question = data.get("question")
    answer = data.get("answer")

    if not question or not answer:
        return jsonify({"reply": "Invalid data"}), 400

    faq = pd.read_csv(FAQ_FILE)
    new_entry = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_entry], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)

    send_sse_message(f"✅ Update from admin: {answer}")
    return jsonify({"reply": "Customer notified."})

@app.route('/stream')
def stream():
    """SSE endpoint for real-time notifications"""
    q = Queue()
    clients.append(q)
    
    def event_stream(queue):
        while True:
            msg = queue.get()
            yield f"data: {msg}\n\n"
    
    return Response(event_stream(q), mimetype="text/event-stream")

@app.route('/pending', methods=['GET'])
def get_pending():
    """Get all pending questions"""
    try:
        pending = pd.read_csv(PENDING_FILE)
        questions = pending['question'].tolist()
        return jsonify(questions)
    except Exception as e:
        print(f"⚠️ Error: {e}")
        return jsonify([])

# ==================== RUN ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    
    print(f"""
    ╔════════════════════════════════════════╗
    ║   Papsi Chatbot - Render Optimized    ║
    ║   Port: {port}                            ║
    ║   AI Model: Loading in background...  ║
    ╚════════════════════════════════════════╝
    """)
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
