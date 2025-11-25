"""
Papsi Repair Shop - Ultra-Lightweight Chatbot API
OPTIMIZED FOR RENDER 512MB - Using Ultra-Light AI Models
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

# Use absolute paths
FAQ_FILE = BASE_DIR / 'faq.csv'
PENDING_FILE = BASE_DIR / 'pending_questions.csv'

# Initialize files
def initialize_files():
    try:
        if not FAQ_FILE.exists():
            sample_faq = pd.DataFrame({
                'question': [
                    'What are your operating hours?',
                    'How much does an oil change cost?',
                    'My car is making strange noises',
                    'Brakes are squeaking',
                    'Engine won\'t start'
                ],
                'answer': [
                    'We\'re open Monday to Saturday, 8 AM to 6 PM.',
                    'Basic oil change starts at ₱800. Synthetic oil changes start at ₱1,200.',
                    'That could be various issues. We recommend bringing it in for diagnosis.',
                    'Squeaky brakes often need pad replacement. We offer free brake inspections.',
                    'This could be battery, starter, or fuel system issues. We can diagnose it for you.'
                ]
            })
            sample_faq.to_csv(FAQ_FILE, index=False)
            print("✅ Created sample FAQ file")
        
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print("✅ Created pending questions file")
            
        # Verify we can read the files
        faq_test = pd.read_csv(FAQ_FILE)
        pending_test = pd.read_csv(PENDING_FILE)
        print(f"📁 FAQ File: {len(faq_test)} entries")
        print(f"📁 Pending File: {len(pending_test)} entries")
        
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

# ==================== ULTRA-LIGHTWEIGHT AI MODEL ====================
model = None
model_loaded = False
model_type = None  # 'sentence_transformers', 'tfidf', or None

def load_ultra_light_model():
    """Load the lightest possible AI model with fallbacks"""
    global model, model_loaded, model_type
    
    print("🚀 Loading ULTRA-LIGHT AI model...")
    
    # Force garbage collection first
    gc.collect()
    
    try:
        # OPTION 1: Lightest Sentence Transformer (22MB)
        from sentence_transformers import SentenceTransformer
        print("📥 Downloading all-MiniLM-L6-v2 (22MB)...")
        model = SentenceTransformer('all-MiniLM-L6-v2')
        model_loaded = True
        model_type = 'sentence_transformers'
        print("✅ Ultra-light SentenceTransformer loaded! (22MB)")
        return
        
    except Exception as e:
        print(f"⚠️ SentenceTransformer failed: {e}")
    
    try:
        # OPTION 2: TF-IDF (Almost no memory)
        from sklearn.feature_extraction.text import TfidfVectorizer
        from sklearn.metrics.pairwise import cosine_similarity
        model = {
            'vectorizer': TfidfVectorizer(),
            'similarity': cosine_similarity
        }
        model_loaded = True
        model_type = 'tfidf'
        print("✅ TF-IDF model loaded (minimal memory)")
        return
        
    except Exception as e:
        print(f"⚠️ TF-IDF failed: {e}")
    
    # OPTION 3: No model - pure keyword matching
    print("🔧 No AI model - using enhanced keyword matching only")
    model_loaded = False
    model_type = None

# Load model in background thread to prevent blocking
def load_model_background():
    """Load model in background to prevent app startup blocking"""
    try:
        load_ultra_light_model()
    except Exception as e:
        print(f"💥 Model loading thread crashed: {e}")
        model_loaded = False

print("🔄 Starting model loading in background...")
model_thread = threading.Thread(target=load_model_background)
model_thread.daemon = True
model_thread.start()

# ==================== SSE ====================
clients = []

def send_sse_message(data):
    """Send SSE message to all connected clients"""
    for q in clients[:]:
        try:
            q.put(data, timeout=0.2)
        except Exception:
            clients.remove(q)

# ==================== HYBRID SEARCH SYSTEM ====================
def preprocess_text(text):
    """Clean and preprocess text for better matching"""
    if not isinstance(text, str):
        return ""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)  # Remove punctuation
    return text

def hybrid_search_faq(user_message, faq_data):
    """Smart hybrid search: AI first, then keyword fallback"""
    
    # Try AI model first if available
    if model_loaded:
        if model_type == 'sentence_transformers':
            result = semantic_search(user_message, faq_data)
            if result[0] is not None:
                print(f"🤖 AI found match (score: {result[1]:.3f})")
                return result
        elif model_type == 'tfidf':
            result = tfidf_search(user_message, faq_data)
            if result[0] is not None:
                print(f"🤖 TF-IDF found match (score: {result[1]:.3f})")
                return result
    
    # Fallback to keyword matching
    print("🔍 AI not available, using keyword matching")
    return keyword_search(user_message, faq_data)

def semantic_search(user_message, faq_data):
    """Ultra-light semantic search with memory optimization"""
    if not model_loaded or model_type != 'sentence_transformers':
        return None, 0
        
    try:
        from sentence_transformers import util
        
        # Clean and prepare data
        faq_data = faq_data.dropna(subset=['question', 'answer'])
        faq_data = faq_data[faq_data['question'].str.strip() != '']
        faq_data = faq_data[faq_data['answer'].str.strip() != '']
        
        if len(faq_data) == 0:
            return None, 0
            
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        
        # Encode with small batch size to save memory
        faq_embeddings = model.encode(questions, convert_to_tensor=True, batch_size=4, show_progress_bar=False)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        
        # Calculate similarities
        similarities = util.cos_sim(user_emb, faq_embeddings)[0]
        best_idx = similarities.argmax()
        best_score = float(similarities[best_idx])
        
        if best_score >= 0.3:  # Slightly higher threshold for better accuracy
            return answers[best_idx], best_score
        return None, best_score
        
    except Exception as e:
        print(f"⚠️ Semantic search error: {e}")
        return None, 0

def tfidf_search(user_message, faq_data):
    """Lightweight TF-IDF search"""
    try:
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        
        # Fit TF-IDF
        vectorizer = model['vectorizer']
        tfidf_matrix = vectorizer.fit_transform(questions)
        
        # Transform user message
        user_vector = vectorizer.transform([user_message])
        
        # Calculate similarities
        similarities = model['similarity'](user_vector, tfidf_matrix)[0]
        best_idx = similarities.argmax()
        best_score = similarities[best_idx]
        
        if best_score > 0.2:
            return answers[best_idx], best_score
        return None, best_score
        
    except Exception as e:
        print(f"⚠️ TF-IDF search error: {e}")
        return None, 0

def keyword_search(user_message, faq_data):
    """Enhanced keyword matching as final fallback"""
    try:
        user_clean = preprocess_text(user_message)
        user_words = set(user_clean.split())
        
        # Remove very short words
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
                
            # Bonus for longer matching words
            for word in common_words:
                if len(word) > 4:
                    score += 0.1
            
            if score > best_score and score > 0.2:
                best_score = score
                best_match = answer
                
        return best_match, best_score
        
    except Exception as e:
        print(f"⚠️ Keyword search error: {e}")
        return None, 0

def hybrid_search_services(user_message, services):
    """Hybrid service search"""
    user_clean = preprocess_text(user_message)
    user_words = [word for word in user_clean.split() if len(word) > 2]
    
    if not user_words:
        return []
        
    matches = []
    
    for service in services:
        name = preprocess_text(service['service_name'])
        desc = preprocess_text(service['description'])
        
        score = 0
        
        # Keyword matching
        for keyword in user_words:
            if keyword in name:
                score += 2.0
            elif keyword in desc:
                score += 1.0
                
        # Exact phrase bonus
        if user_clean in name:
            score += 3.0
        elif user_clean in desc:
            score += 2.0
            
        if score > 0.5:
            matches.append((service, score))
            
    # Return top 3 matches
    matches.sort(key=lambda x: x[1], reverse=True)
    return matches[:3]

# ==================== DATABASE ====================

def get_db_connection():
    """Get database connection with error handling"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as e:
        print(f"⚠️ DB connection error: {e}")
        return None

def get_services_from_db():
    """Get services from database with error handling"""
    try:
        conn = get_db_connection()
        if not conn:
            print("❌ No database connection")
            return []
            
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT service_name, description, duration, price FROM services WHERE is_archived = 0")
        services = cursor.fetchall()
        cursor.close()
        conn.close()
        
        print(f"✅ Loaded {len(services)} services from database")
        return services
        
    except Exception as e:
        print(f"⚠️ Service query error: {e}")
        return []

# ==================== PENDING QUESTIONS ====================

def save_pending_question(question):
    """Save pending question"""
    try:
        if not isinstance(question, str) or not question.strip():
            return False
            
        question_clean = question.strip()
        
        # Read current pending questions
        if PENDING_FILE.exists():
            pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        else:
            pending = pd.DataFrame(columns=['question'])
        
        # Clean data
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].astype(str).str.strip()
        pending = pending[pending['question'] != '']
        
        # Check for duplicates
        if question_clean in pending['question'].values:
            print(f"⏭️ Question already pending: {question_clean}")
            return False
        
        # Add new question
        new_row = pd.DataFrame({'question': [question_clean]})
        pending = pd.concat([pending, new_row], ignore_index=True)
        
        # Save
        pending.to_csv(PENDING_FILE, index=False)
        print(f"✅ Saved to pending: {question_clean}")
        return True
        
    except Exception as e:
        print(f"❌ Error saving pending question: {e}")
        return False

def get_pending_questions():
    """Get all pending questions"""
    try:
        if not PENDING_FILE.exists():
            return []
            
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].astype(str).str.strip()
        pending = pending[pending['question'] != '']
        
        questions = pending['question'].tolist()
        return questions
        
    except Exception as e:
        print(f"❌ Error reading pending questions: {e}")
        return []

def remove_pending_question(question):
    """Remove question from pending"""
    try:
        if not PENDING_FILE.exists():
            return
            
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        pending = pending[pending['question'] != question]
        pending.to_csv(PENDING_FILE, index=False)
        print(f"🗑️ Removed from pending: {question}")
        
    except Exception as e:
        print(f"❌ Error removing pending question: {e}")

# ==================== ENDPOINTS ====================

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "model_loaded": model_loaded,
        "model_type": model_type,
        "mode": "ultra-light-ai"
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop Chatbot API",
        "status": "running",
        "version": "ultra-light-ai-1.0",
        "model_status": "loaded" if model_loaded else "loading",
        "model_type": model_type
    }), 200

@app.route('/test', methods=['GET'])
def test():
    """Test endpoint to verify all components"""
    try:
        # Test files
        faq_exists = FAQ_FILE.exists()
        pending_exists = PENDING_FILE.exists()
        
        # Test database
        services = get_services_from_db()
        db_works = len(services) >= 0
        
        # Test model
        model_status = "loaded" if model_loaded else "failed"
        
        return jsonify({
            "status": "success",
            "faq_file": faq_exists,
            "pending_file": pending_exists,
            "database": db_works,
            "services_count": len(services),
            "model_status": model_status,
            "model_type": model_type
        })
        
    except Exception as e:
        return jsonify({
            "status": "error",
            "error": str(e)
        }), 500

@app.route('/chat', methods=['POST'])
def chat():
    """Customer chatbot endpoint with hybrid AI search"""
    try:
        # Get and validate input
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message in JSON format."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Please describe your vehicle problem."})

        print(f"📨 Received: {user_message}")
        reply_parts = []
        
        # Check FAQ with hybrid search
        try:
            faq_data = pd.read_csv(FAQ_FILE)
            faq_data = faq_data.dropna(subset=['question', 'answer'])
            faq_data = faq_data[faq_data['question'].str.strip() != '']
            faq_data = faq_data[faq_data['answer'].str.strip() != '']
            
            faq_reply, score = hybrid_search_faq(user_message, faq_data)
            
            if faq_reply:
                reply_parts.append(f"🔧 {faq_reply}")
                print(f"✅ Found FAQ match (score: {score:.3f})")
                
        except Exception as e:
            print(f"⚠️ FAQ processing error: {e}")
            faq_reply = None

        # Check Services
        try:
            services = get_services_from_db()
            top_services = hybrid_search_services(user_message, services)
            
            if top_services:
                reply_parts.append("🧰 Based on your concern, here are some services:")
                for service, score in top_services:
                    part = (
                        f"• **{service['service_name']}**\n"
                        f"📝 {service['description']}\n"
                        f"🕒 Duration: {service['duration']}\n"
                        f"💰 Price: ₱{float(service['price']):,.2f}"
                    )
                    reply_parts.append(part)
                print(f"✅ Found {len(top_services)} service matches")
                
        except Exception as e:
            print(f"⚠️ Service processing error: {e}")
            top_services = []

        # If no matches found, forward to admin
        if not reply_parts:
            saved = save_pending_question(user_message)
            
            if saved:
                reply_parts.append(
                    "🤖 I'm not sure about that yet. I've forwarded your question to our admin. "
                    "You'll be updated soon!"
                )
                print(f"📤 Forwarded to admin: {user_message}")
            else:
                reply_parts.append(
                    "🤖 Your question is already being reviewed by our admin. "
                    "You'll be updated soon!"
                )

        reply = "\n\n".join(reply_parts)
        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Critical error in /chat: {e}")
        traceback.print_exc()
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

        # Add to FAQ
        try:
            faq = pd.read_csv(FAQ_FILE)
            # Remove existing entry if any
            faq = faq[faq['question'] != question]
            new_row = pd.DataFrame({'question': [question], 'answer': [answer]})
            faq = pd.concat([faq, new_row], ignore_index=True)
            faq.to_csv(FAQ_FILE, index=False)
            print(f"✅ Added to FAQ: {question}")
        except Exception as e:
            print(f"⚠️ FAQ save error: {e}")

        # Remove from pending
        remove_pending_question(question)

        # Notify customers
        notification_message = f"✅ **Update on your question:**\n\n\"{question}\"\n\n**Admin Response:** {answer}"
        send_sse_message(notification_message)

        # Get next question
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
    def event_stream():
        q = Queue()
        clients.append(q)
        try:
            while True:
                msg = q.get()
                yield f"data: {json.dumps({'message': msg})}\n\n"
        except GeneratorExit:
            clients.remove(q)
    
    return Response(event_stream(), mimetype="text/event-stream")

# ==================== RUN ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n🚀 Starting ULTRA-LIGHT Papsi Chatbot on port {port}")
    print("💡 Using hybrid AI system with fallbacks")
    print("🎯 Model priority: SentenceTransformer → TF-IDF → Keyword Matching")
    print("📊 Memory optimized for Render 512MB")
    
    # Wait a bit for model to load
    import time
    time.sleep(2)
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
