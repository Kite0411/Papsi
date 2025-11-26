"""
Papsi Repair Shop - Unified Chatbot API with AI Model
FIXED: Supports 3-column FAQ and optimized for Render 512MB
"""

from flask import Flask, request, jsonify, Response
from flask_cors import CORS
import pandas as pd
import os
import mysql.connector
from queue import Queue
import threading
from pathlib import Path
from dotenv import load_dotenv
import json
import re
import traceback
import gc

load_dotenv()
app = Flask(__name__)

CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "*").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type"]
    }
})

# ==================== CONFIGURATION ====================

DB_CONFIG = {
    'host': os.environ.get('DB_HOST', '127.0.0.1'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'autorepair_db'),
    'port': int(os.environ.get('DB_PORT', 3306))
}

BASE_DIR = Path(__file__).resolve().parent
ROOT_DIR = BASE_DIR.parent

def resolve_data_file(env_var, default_name):
    env_path = os.environ.get(env_var)
    if env_path:
        return Path(env_path).expanduser().resolve()
    repo_candidate = (ROOT_DIR / default_name).resolve()
    if repo_candidate.exists():
        return repo_candidate
    return (BASE_DIR / default_name).resolve()

FAQ_FILE = resolve_data_file('FAQ_FILE_PATH', 'faq.csv')
PENDING_FILE = resolve_data_file('PENDING_FILE_PATH', 'pending_questions.csv')

FAQ_FILE.parent.mkdir(parents=True, exist_ok=True)
PENDING_FILE.parent.mkdir(parents=True, exist_ok=True)

# Initialize files with 3-column support
def initialize_files():
    try:
        if not FAQ_FILE.exists():
            # Create with 3 columns to match your structure
            pd.DataFrame(columns=['question', 'answer', 'follow_up']).to_csv(FAQ_FILE, index=False)
            print("✅ Created empty 3-column FAQ file")
        else:
            # Check existing FAQ structure
            faq_data = pd.read_csv(FAQ_FILE)
            print(f"✅ Using existing FAQ with {len(faq_data)} entries")
            print(f"📊 FAQ columns: {faq_data.columns.tolist()}")
            
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print("✅ Created pending questions file")
            
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

print(f"📁 FAQ File: {FAQ_FILE}")
print(f"📁 Pending File: {PENDING_FILE}")

# ==================== AI MODEL - SMALLER FOR 512MB RAM ====================
model = None
model_loading = False
model_loaded = False

def load_optimized_model():
    """Load SMALLER AI model for 512MB RAM"""
    global model, model_loading, model_loaded
    
    if model_loaded or model_loading:
        return
        
    model_loading = True
    print("🚀 Loading SMALLER AI model for 512MB RAM...")
    
    try:
        # Force garbage collection before loading
        gc.collect()
        
        # Use smaller model that fits in 512MB
        from sentence_transformers import SentenceTransformer
        model = SentenceTransformer('all-MiniLM-L6-v2')  # Only 80MB download, ~200MB in memory
        
        model_loaded = True
        print("✅ SMALLER AI model loaded successfully! (~200MB RAM)")
        
    except Exception as e:
        print(f"❌ Model loading failed: {e}")
        model_loaded = False
    finally:
        model_loading = False

# Load model with error handling
try:
    load_optimized_model()
except Exception as e:
    print(f"💥 Model loading crashed: {e}")
    model_loaded = False

# ==================== SSE ====================
clients = []
question_subscriptions = {}

def send_sse_message(data, question_filter=None):
    """Send SSE message to all clients or specific question subscribers"""
    for q in clients[:]:
        try:
            if question_filter and question_filter in question_subscriptions:
                if q in question_subscriptions[question_filter]:
                    q.put(data, timeout=0.2)
            elif not question_filter:
                q.put(data, timeout=0.2)
        except Exception:
            clients.remove(q)

def subscribe_to_question(client_queue, question):
    """Subscribe a client to updates for a specific question"""
    if question not in question_subscriptions:
        question_subscriptions[question] = []
    if client_queue not in question_subscriptions[question]:
        question_subscriptions[question].append(client_queue)

# ==================== ENHANCED SEARCH SYSTEM ====================

def preprocess_text(text):
    """Clean and preprocess text for better matching"""
    if not isinstance(text, str):
        return ""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)
    return text

def hybrid_search_faq(user_message, faq_data):
    """Hybrid search: AI first, then keyword fallback"""
    print(f"🔍 Searching FAQ for: '{user_message}'")
    
    # Try AI model first
    if model_loaded:
        ai_result, ai_score = semantic_search_faq(user_message, faq_data)
        if ai_result is not None:
            print(f"🤖 AI found match (score: {ai_score:.3f})")
            return ai_result, ai_score
        else:
            print(f"🤖 AI no match (best score: {ai_score:.3f})")
    
    # Fallback to keyword search
    print("🔧 Using keyword fallback search")
    return keyword_search_faq(user_message, faq_data)

def semantic_search_faq(user_message, faq_data):
    """Enhanced semantic search with better error handling"""
    if not model_loaded or model is None or len(faq_data) == 0:
        return None, 0
        
    try:
        from sentence_transformers import util
        
        # Handle 3-column FAQ format
        faq_data = faq_data.dropna(subset=['question', 'answer'])
        faq_data = faq_data[faq_data['question'].str.strip() != '']
        faq_data = faq_data[faq_data['answer'].str.strip() != '']
        
        if len(faq_data) == 0:
            return None, 0
            
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        
        # Encode with small batch size for memory efficiency
        faq_embeddings = model.encode(questions, convert_to_tensor=True, batch_size=8, show_progress_bar=False)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        
        # Calculate similarities
        similarities = util.cos_sim(user_emb, faq_embeddings)[0]
        
        # Find best match with keyword boosting
        user_keywords = set(user_message.lower().split())
        boosted_scores = []
        
        for i, question in enumerate(questions):
            score = float(similarities[i])
            question_keywords = set(question.lower().split())
            matches = len(user_keywords & question_keywords)
            
            # Boost score based on keyword matches
            if matches > 0:
                boost = min(matches * 0.1, 0.3)
                score += boost
                
            boosted_scores.append((i, score))
        
        # Get best match
        best_idx, best_score = max(boosted_scores, key=lambda x: x[1])
        
        # Lower threshold for better matching (0.2 instead of 0.25)
        if best_score >= 0.2:
            return answers[best_idx], best_score
            
        return None, best_score
        
    except Exception as e:
        print(f"⚠️ Semantic search error: {e}")
        return None, 0

def keyword_search_faq(user_message, faq_data):
    """Enhanced keyword search as fallback"""
    try:
        user_clean = preprocess_text(user_message)
        user_words = set(user_clean.split())
        
        # Remove short words but keep important ones
        user_words = {word for word in user_words if len(word) > 2}
        
        if not user_words:
            return None, 0
            
        best_match = None
        best_score = 0
        
        for idx, row in faq_data.iterrows():
            question = preprocess_text(str(row['question']))
            answer = str(row['answer'])
            
            question_words = set(question.split())
            common_words = user_words.intersection(question_words)
            
            if not common_words:
                continue
                
            # Calculate match score
            score = len(common_words) / len(user_words)
            
            # Bonus for exact phrase matches
            if user_clean in question:
                score += 0.5
                
            # Bonus for important auto repair keywords
            important_words = {'oil', 'change', 'brake', 'engine', 'start', 'battery', 'tire', 'wheel', 'ac'}
            for word in common_words:
                if word in important_words:
                    score += 0.2
                elif len(word) > 4:
                    score += 0.1
            
            # Lower threshold for keyword matching
            if score > best_score and score > 0.15:
                best_score = score
                best_match = answer
                
        return best_match, best_score
        
    except Exception as e:
        print(f"⚠️ Keyword search error: {e}")
        return None, 0

def hybrid_search_services(user_message, services):
    """Hybrid service search"""
    if not services:
        return []
        
    # Try AI first if available
    if model_loaded:
        ai_results = semantic_search_services(user_message, services)
        if ai_results:
            return ai_results
    
    # Fallback to keyword search
    return keyword_search_services(user_message, services)

def semantic_search_services(user_message, services):
    """Semantic service search"""
    if not model_loaded or model is None or not services:
        return []
        
    try:
        from sentence_transformers import util
        
        text_data = [f"{s['service_name']} {s['description']}" for s in services]
        service_embeddings = model.encode(text_data, convert_to_tensor=True, batch_size=8)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        
        similarities = util.cos_sim(user_emb, service_embeddings)[0]
        scores = similarities.cpu().tolist()
        
        ranked = sorted(enumerate(scores), key=lambda x: x[1], reverse=True)
        top_services = []
        
        for idx, score in ranked[:3]:
            if score > 0.2:  # Lower threshold
                top_services.append((services[idx], score))
                
        return top_services
        
    except Exception as e:
        print(f"⚠️ Service semantic search error: {e}")
        return []

def keyword_search_services(user_message, services):
    """Keyword service search"""
    user_clean = user_message.lower()
    keywords = user_clean.split()
    
    matches = []
    for service in services:
        name = service['service_name'].lower()
        desc = service['description'].lower()
        
        score = 0
        for kw in keywords:
            if len(kw) > 2:  # Only substantial words
                if kw in name:
                    score += 2
                elif kw in desc:
                    score += 1
                    
        if score > 0:
            matches.append((service, score))
            
    matches.sort(key=lambda x: x[1], reverse=True)
    return matches[:3]

# ==================== DATABASE ====================

def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as e:
        print(f"⚠️ DB error: {e}")
        return None

def get_services_from_db():
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
        print(f"⚠️ Service query error: {e}")
        return []

# ==================== PENDING QUESTIONS ====================

def save_pending_question(question):
    """Save pending question - THREAD-SAFE VERSION"""
    try:
        # Read current state
        if PENDING_FILE.exists():
            try:
                pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
            except:
                pending = pd.DataFrame(columns=['question'])
        else:
            pending = pd.DataFrame(columns=['question'])
        
        # Clean up
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].str.strip()
        pending = pending[pending['question'] != '']
        pending = pending[pending['question'] != 'question']
        
        # Check duplicate
        question_clean = question.strip()
        if question_clean in pending['question'].values:
            print(f"⏭️ Already pending: {question_clean}")
            return False
        
        # Add new
        new_row = pd.DataFrame({'question': [question_clean]})
        pending = pd.concat([pending, new_row], ignore_index=True)
        
        # Save atomically
        pending.to_csv(PENDING_FILE, index=False, encoding='utf-8')
        
        print(f"✅ SAVED TO PENDING: {question_clean}")
        return True
        
    except Exception as e:
        print(f"⚠️ ERROR saving pending: {e}")
        return False

def get_pending_questions():
    """Get all pending questions"""
    try:
        if not PENDING_FILE.exists():
            return []
        
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str}, keep_default_na=False)
        
        # Clean
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].str.strip()
        pending = pending[pending['question'] != '']
        pending = pending[pending['question'] != 'question']
        
        questions = pending['question'].tolist()
        return questions
        
    except Exception as e:
        print(f"⚠️ ERROR reading pending: {e}")
        return []

def remove_pending_question(question):
    """Remove question from pending"""
    try:
        if not PENDING_FILE.exists():
            return
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        pending = pending[pending['question'] != question]
        pending.to_csv(PENDING_FILE, index=False)
        print(f"🗑️ Removed: {question}")
    except Exception as e:
        print(f"⚠️ Remove error: {e}")

# ==================== ENDPOINTS ====================

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "model_loaded": model_loaded,
        "faq_entries": "using hybrid AI + keyword search",
        "memory_optimized": True
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop Chatbot API",
        "status": "running",
        "model_ready": model_loaded,
        "search_mode": "hybrid (AI + keywords)"
    }), 200

@app.route('/debug', methods=['GET'])
def debug():
    """Debug endpoint to check system status"""
    try:
        faq_data = pd.read_csv(FAQ_FILE) if FAQ_FILE.exists() else pd.DataFrame()
        pending_data = pd.read_csv(PENDING_FILE) if PENDING_FILE.exists() else pd.DataFrame()
        services = get_services_from_db()
        
        return jsonify({
            "faq_file_exists": FAQ_FILE.exists(),
            "faq_entries": len(faq_data),
            "faq_columns": faq_data.columns.tolist() if FAQ_FILE.exists() else [],
            "pending_questions": len(pending_data),
            "services_available": len(services),
            "model_loaded": model_loaded,
            "memory_usage": "optimized"
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/chat', methods=['POST'])
def chat():
    """Customer chatbot endpoint - ENHANCED WITH HYBRID SEARCH"""
    try:
        data = request.get_json()
        user_message = data.get('message', '').strip()

        if not user_message:
            return jsonify({'reply': "Please describe your vehicle problem."})

        print(f"📨 Received: '{user_message}'")
        reply_parts = []
        
        # Check FAQ with HYBRID search
        try:
            faq_data = pd.read_csv(FAQ_FILE)
            # Handle 3-column format
            faq_data = faq_data.dropna(subset=['question', 'answer'])
            faq_data = faq_data[faq_data['question'].str.strip() != '']
            faq_data = faq_data[faq_data['answer'].str.strip() != '']
            
            faq_reply, score = hybrid_search_faq(user_message, faq_data)
            
        except Exception as e:
            print(f"⚠️ FAQ error: {e}")
            faq_reply = None

        # Check Services with HYBRID search
        services = get_services_from_db()
        top_services = hybrid_search_services(user_message, services)

        # Build reply
        if faq_reply:
            reply_parts.append(f"🔧 {faq_reply}")

        if top_services:
            reply_parts.append("🧰 Based on your concern, here are some services:")
            for s, score in top_services:
                part = (
                    f"• **{s['service_name']}**\n"
                    f"📝 {s['description']}\n"
                    f"🕒 Duration: {s['duration']}\n"
                    f"💰 Price: ₱{float(s['price']):,.2f}"
                )
                reply_parts.append(part)

        if not faq_reply and not top_services:
            # Forward to admin
            saved = save_pending_question(user_message)
            
            if saved:
                reply_parts.append(
                    "🤖 I'm not sure about that yet. I've forwarded your question to our admin. "
                    "You'll be updated soon!"
                )
                print(f"📤 FORWARDED TO ADMIN: {user_message}")
            else:
                reply_parts.append(
                    "🤖 Your question is already being reviewed by our admin. "
                    "You'll be updated soon!"
                )

        reply = "\n\n".join(reply_parts)
        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Critical error in /chat: {e}")
        return jsonify({
            'reply': "I'm experiencing technical difficulties. Please try again in a moment."
        }), 500

# ==================== ADMIN ENDPOINTS ====================

current_question = None

@app.route('/pending', methods=['GET'])
def get_pending():
    """Get all pending questions"""
    questions = get_pending_questions()
    return jsonify(questions)

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """Get the next pending question for admin"""
    global current_question
    
    questions = get_pending_questions()
    
    if not questions:
        current_question = None
        return jsonify({'new': False})

    if current_question is None or current_question not in questions:
        current_question = questions[0]
        return jsonify({'new': True, 'question': current_question})

    if current_question == questions[0]:
        return jsonify({'new': False})
    
    current_question = questions[0]
    return jsonify({'new': True, 'question': current_question})

@app.route('/admin_chat', methods=['POST'])
def admin_chat():
    """Admin chatbot endpoint"""
    global current_question
    try:
        data = request.get_json()
        message = data.get('message', '').strip()

        if not message:
            return jsonify({'reply': 'Please type something.'})

        if current_question is None:
            questions = get_pending_questions()
            if not questions:
                return jsonify({'reply': '✅ No pending questions.'})
            current_question = questions[0]
            return jsonify({'reply': f"❓ {current_question}\n\nProvide your answer:"})

        # Save answer
        question = current_question
        answer = message

        try:
            # Handle 3-column FAQ format when saving
            faq = pd.read_csv(FAQ_FILE)
            faq = faq.drop_duplicates(subset=['question'], keep='last')
            faq = faq[faq['question'] != question]
            
            # Add new entry with 3-column format
            new_row = pd.DataFrame({
                'question': [question], 
                'answer': [answer],
                'follow_up': ['']
            })
            faq = pd.concat([faq, new_row], ignore_index=True)
            faq.to_csv(FAQ_FILE, index=False)
            print(f"✅ Saved to FAQ: {question}")
        except Exception as e:
            print(f"⚠️ FAQ save error: {e}")

        # Remove from pending
        remove_pending_question(question)

        # Notify customers
        notification_message = f"✅ **Update on your question:**\n\n\"{question}\"\n\n**Admin Response:** {answer}"
        send_sse_message(notification_message, question)

        # Check for next
        current_question = None
        questions = get_pending_questions()

        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"✅ Answer saved and customer notified!\n\nNext question:\n❓ {next_q}\nProvide your answer:"
        else:
            reply = "✅ Answer saved and customer notified! No more pending questions."

        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Admin chat error: {e}")
        return jsonify({'reply': 'Error processing request.'}), 500

@app.route('/stream')
def stream():
    """SSE endpoint for customer updates"""
    q = Queue()
    clients.append(q)
    
    def event_stream(queue):
        while True:
            msg = queue.get()
            yield f"data: {json.dumps({'message': msg})}\n\n"
    
    return Response(event_stream(q), mimetype="text/event-stream")

# ==================== RUN ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n🚀 Starting Papsi Chatbot on port {port}")
    print(f"🤖 AI Model: {'LOADED' if model_loaded else 'NOT LOADED'}")
    print("💡 Using HYBRID search (AI + keywords)")
    print("📊 Memory optimized for Render 512MB")
    print("🎯 Supports 3-column FAQ format")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
