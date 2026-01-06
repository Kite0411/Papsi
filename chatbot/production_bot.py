"""
Papsi Repair Shop - Ultra-Lightweight Chatbot for 512MB RAM
FAQ.csv location: PARENT DIRECTORY (outside chatbot folder)
Version: 5.0 - FINAL
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import os
import mysql.connector
from mysql.connector import pooling
from pathlib import Path
from dotenv import load_dotenv
import traceback
import time
import numpy as np
from sentence_transformers import SentenceTransformer
import torch

load_dotenv()
app = Flask(__name__)

CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "*").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type", "Authorization", "X-Customer-ID"]
    }
})

# ==================== DATABASE CONFIG ====================

DB_CONFIG = {
    'host': os.environ.get('DB_HOST', '127.0.0.1'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'autorepair_db'),
    'port': int(os.environ.get('DB_PORT', 3306))
}

# ==================== AI MODEL (512MB RAM OPTIMIZED) ====================

print("🔄 Loading AI model for 512MB RAM...")
try:
    model = SentenceTransformer('all-MiniLM-L6-v2', device='cpu')
    model = model.to('cpu')
    model.eval()
    torch.set_grad_enabled(False)
    torch.set_num_threads(1)
    
    print("✅ Model loaded: all-MiniLM-L6-v2 (80MB, CPU-only)")
    
except Exception as e:
    print(f"❌ Model loading failed: {e}")
    model = None

# ==================== DATABASE POOL ====================

try:
    db_pool = pooling.MySQLConnectionPool(
        pool_name="chatbot_pool",
        pool_size=2,
        pool_reset_session=True,
        **DB_CONFIG
    )
    print("✅ Database pool: 2 connections")
except Exception as e:
    print(f"⚠️ Database pool failed: {e}")
    db_pool = None

# ==================== FILE PATHS ====================

# Get current file location
CURRENT_DIR = Path(__file__).resolve().parent  # chatbot/
ROOT_DIR = CURRENT_DIR.parent                   # parent directory

# FAQ.csv is in PARENT directory (outside chatbot folder)
FAQ_FILE = ROOT_DIR / 'faq.csv'
PENDING_FILE = CURRENT_DIR / 'pending_questions.csv'

print(f"\n{'='*60}")
print(f"📁 Current Directory: {CURRENT_DIR}")
print(f"📁 Parent Directory: {ROOT_DIR}")
print(f"📁 FAQ File: {FAQ_FILE}")
print(f"📁 FAQ Exists: {FAQ_FILE.exists()}")
print(f"{'='*60}\n")

# ==================== INITIALIZE FILES ====================

def initialize_files():
    """Initialize CSV files"""
    try:
        # Check FAQ in parent directory
        if FAQ_FILE.exists():
            faq_data = pd.read_csv(FAQ_FILE)
            print(f"✅ FAQ loaded: {len(faq_data)} entries from {FAQ_FILE}")
        else:
            print(f"⚠️ FAQ file not found at {FAQ_FILE}")
            print(f"   Creating empty FAQ file...")
            pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
            print(f"✅ Created empty FAQ at {FAQ_FILE}")
            
        # Create pending file in chatbot directory
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print(f"✅ Created pending file at {PENDING_FILE}")
        
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

# ==================== FAQ CACHE (512MB OPTIMIZED) ====================

faq_cache = {
    'data': None,
    'embeddings': None,
    'last_update': 0,
    'file_mtime': 0
}

FAQ_CACHE_TTL = 300  # 5 minutes
MAX_FAQ_ENTRIES = 100

def load_faq_with_cache():
    """Load FAQ from PARENT directory with caching"""
    global faq_cache
    
    try:
        # Check if FAQ file exists
        if not FAQ_FILE.exists():
            print(f"⚠️ FAQ file not found at {FAQ_FILE}")
            return None, None
        
        # Get file modification time
        current_mtime = FAQ_FILE.stat().st_mtime
        current_time = time.time()
        
        # Return cached data if valid
        if (faq_cache['data'] is not None and 
            faq_cache['embeddings'] is not None and
            current_mtime == faq_cache['file_mtime'] and
            (current_time - faq_cache['last_update']) < FAQ_CACHE_TTL):
            return faq_cache['data'], faq_cache['embeddings']
        
        # Load fresh data from PARENT directory
        print(f"📂 Loading FAQ from: {FAQ_FILE}")
        faq_data = pd.read_csv(FAQ_FILE)
        faq_data = faq_data.dropna(subset=['question', 'answer'])
        
        if len(faq_data) == 0:
            print("⚠️ FAQ file is empty!")
            return None, None
        
        # Limit entries for memory
        if len(faq_data) > MAX_FAQ_ENTRIES:
            print(f"⚠️ Limiting to {MAX_FAQ_ENTRIES} entries")
            faq_data = faq_data.head(MAX_FAQ_ENTRIES)
        
        print(f"✅ Loaded {len(faq_data)} FAQ entries")
        
        # Generate embeddings
        embeddings = None
        if model is not None:
            try:
                print("🔄 Generating embeddings...")
                questions = faq_data['question'].tolist()
                embeddings = model.encode(
                    questions, 
                    convert_to_tensor=False, 
                    show_progress_bar=False, 
                    batch_size=8
                )
                print(f"✅ Embeddings generated for {len(questions)} questions")
            except Exception as e:
                print(f"⚠️ Embedding error: {e}")
        
        # Update cache
        faq_cache['data'] = faq_data
        faq_cache['embeddings'] = embeddings
        faq_cache['last_update'] = current_time
        faq_cache['file_mtime'] = current_mtime
        
        return faq_data, embeddings
        
    except Exception as e:
        print(f"❌ FAQ loading error: {e}")
        traceback.print_exc()
        return None, None

# ==================== SEMANTIC FAQ SEARCH ====================

def search_faq(user_message, threshold=0.50):
    """Search FAQ using semantic similarity"""
    try:
        faq_data, faq_embeddings = load_faq_with_cache()
        
        if faq_data is None or len(faq_data) == 0:
            print("❌ No FAQ data")
            return None, 0, None
        
        if faq_embeddings is None or model is None:
            print("⚠️ Falling back to keyword search")
            return fallback_keyword_search(user_message, faq_data)
        
        print(f"🔍 Searching FAQ: '{user_message}'")
        
        # Encode user query
        user_embedding = model.encode(user_message, convert_to_tensor=False)
        
        # Compute similarities
        user_emb_2d = np.array([user_embedding])
        similarities = np.dot(faq_embeddings, user_emb_2d.T).flatten()
        similarities = similarities / (
            np.linalg.norm(faq_embeddings, axis=1) * 
            np.linalg.norm(user_emb_2d)
        )
        
        # Get top 3 for logging
        top_3_idx = similarities.argsort()[-3:][::-1]
        print(f"   Top 3 matches:")
        for idx in top_3_idx:
            q = faq_data.iloc[idx]['question']
            score = similarities[idx]
            print(f"     - '{q[:40]}': {score:.3f}")
        
        # Get best match
        best_idx = similarities.argmax()
        best_score = similarities[best_idx]
        
        if best_score >= threshold:
            answer = faq_data.iloc[best_idx]['answer']
            question = faq_data.iloc[best_idx]['question']
            print(f"✅ MATCH: '{question}' (score: {best_score:.3f})")
            return answer, best_score, question
        else:
            print(f"❌ Below threshold: {best_score:.3f} < {threshold}")
            return None, best_score, None
            
    except Exception as e:
        print(f"❌ Search error: {e}")
        traceback.print_exc()
        return None, 0, None

def fallback_keyword_search(user_message, faq_data):
    """Fallback keyword search"""
    try:
        print("🔄 Using keyword search...")
        user_lower = user_message.lower()
        
        best_match = None
        best_score = 0
        
        for idx, row in faq_data.iterrows():
            question = str(row['question']).lower()
            answer = str(row['answer'])
            
            # Word overlap
            user_words = set(user_lower.split())
            question_words = set(question.split())
            overlap = len(user_words & question_words)
            score = overlap / max(len(user_words), len(question_words), 1)
            
            if score > best_score:
                best_score = score
                best_match = (answer, score, row['question'])
        
        if best_score > 0.3:
            print(f"✅ Keyword match: {best_score:.3f}")
            return best_match
        
        return None, 0, None
        
    except Exception as e:
        print(f"❌ Keyword search error: {e}")
        return None, 0, None

# ==================== DATABASE FUNCTIONS ====================

def get_db_connection():
    """Get database connection"""
    try:
        if db_pool:
            return db_pool.get_connection()
        else:
            return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"⚠️ DB error: {e}")
        return None

def get_services_from_db():
    """Get services from database"""
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            return []
            
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT service_name, description, duration, price 
            FROM services 
            WHERE is_archived = 0
        """)
        services = cursor.fetchall()
        return services
        
    except Exception as e:
        print(f"⚠️ Service query error: {e}")
        return []
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

def match_services_simple(user_message, services):
    """Simple service matching"""
    matched = []
    user_lower = user_message.lower()
    
    for service in services:
        name = service['service_name'].lower()
        desc = service['description'].lower()
        
        if any(word in user_lower for word in name.split() if len(word) > 3):
            matched.append((service, 0.7))
        elif any(word in desc for word in user_lower.split() if len(word) > 3):
            matched.append((service, 0.5))
    
    matched.sort(key=lambda x: x[1], reverse=True)
    return matched[:3]

# ==================== CUSTOMER SESSION ====================

def get_customer_from_request(request):
    """Get customer from request"""
    customer_id = request.headers.get('X-Customer-ID')
    if not customer_id:
        data = request.get_json()
        if data:
            customer_id = data.get('customer_id')
    
    if customer_id:
        return get_customer_details(customer_id)
    return None

def get_customer_details(customer_id):
    """Get customer details"""
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            return None
        
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT id, user_id, name, phone, email, created_at
            FROM customers
            WHERE id = %s OR user_id = %s
        """, (customer_id, customer_id))
        
        customer = cursor.fetchone()
        if customer:
            print(f"✅ Customer: {customer['name']}")
        
        return customer
        
    except Exception as e:
        print(f"❌ Customer error: {e}")
        return None
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

# ==================== PENDING QUESTIONS ====================

def save_pending_question(question):
    """Save to pending"""
    try:
        if PENDING_FILE.exists():
            pending = pd.read_csv(PENDING_FILE)
        else:
            pending = pd.DataFrame(columns=['question'])
        
        question_clean = question.strip()
        
        if question_clean in pending['question'].values:
            return False
        
        new_row = pd.DataFrame({'question': [question_clean]})
        pending = pd.concat([pending, new_row], ignore_index=True)
        pending.to_csv(PENDING_FILE, index=False)
        
        print(f"📝 Pending: {question_clean}")
        return True
        
    except Exception as e:
        print(f"❌ Pending save error: {e}")
        return False

def get_pending_questions():
    """Get pending questions"""
    try:
        if not PENDING_FILE.exists():
            return []
        
        pending = pd.read_csv(PENDING_FILE)
        return pending['question'].tolist()
        
    except Exception as e:
        return []

def remove_pending_question(question):
    """Remove from pending"""
    try:
        if not PENDING_FILE.exists():
            return
        
        pending = pd.read_csv(PENDING_FILE)
        pending = pending[pending['question'] != question]
        pending.to_csv(PENDING_FILE, index=False)
        
    except Exception as e:
        print(f"❌ Remove error: {e}")

# ==================== MAIN CHAT ENDPOINT ====================

@app.route('/chat', methods=['POST'])
def chat():
    """Main chat: FAQ FIRST → Services → Admin"""
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Hi! How can I help you today?"})

        print(f"\n{'='*60}")
        print(f"📨 Message: '{user_message}'")
        
        customer = get_customer_from_request(request)
        customer_name = customer['name'].split()[0] if customer else None
        
        print(f"👤 Customer: {customer_name or 'Guest'}")
        print(f"{'='*60}")
        
        # 🔥 STEP 1: SEARCH FAQ FIRST
        faq_answer, faq_score, matched_q = search_faq(user_message, threshold=0.50)
        
        if faq_answer:
            greeting = f"Hi {customer_name}! " if customer_name else ""
            response = f"{greeting}{faq_answer}"
            
            print(f"✅ FAQ USED (score: {faq_score:.3f})")
            
            return jsonify({
                'reply': response,
                'type': 'faq',
                'confidence': faq_score,
                'customer_name': customer_name
            })
        
        # 🔥 STEP 2: RECOMMEND SERVICES
        services = get_services_from_db()
        
        if services:
            matched_services = match_services_simple(user_message, services)
            
            if matched_services:
                greeting = f"Hi {customer_name}! " if customer_name else ""
                reply_parts = [f"{greeting}🔧 Based on your concern:\n"]
                
                for service, score in matched_services:
                    part = (
                        f"• **{service['service_name']}**\n"
                        f"  📝 {service['description']}\n"
                        f"  🕒 Duration: {service['duration']}\n"
                        f"  💰 Price: ₱{float(service['price']):,.2f}\n"
                    )
                    reply_parts.append(part)
                
                reply_parts.append("\nWant to book an appointment?")
                
                return jsonify({
                    'reply': "\n".join(reply_parts),
                    'type': 'service',
                    'customer_name': customer_name
                })
        
        # 🔥 STEP 3: FORWARD TO ADMIN
        saved = save_pending_question(user_message)
        greeting = f"Hi {customer_name}! " if customer_name else ""
        
        reply = (
            f"{greeting}🤖 I'm not sure about that. "
            "I've forwarded your question to our mechanic. "
            "Check back soon for an answer!"
        )
        
        return jsonify({
            'reply': reply,
            'type': 'pending',
            'customer_name': customer_name
        })
        
    except Exception as e:
        print(f"💥 Error: {e}")
        traceback.print_exc()
        return jsonify({'reply': "Technical issue. Try again."}), 500

# ==================== ADMIN ENDPOINTS ====================

current_question = None

@app.route('/pending', methods=['GET'])
def get_pending():
    """Get pending questions"""
    questions = get_pending_questions()
    return jsonify(questions)

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """Get next question"""
    global current_question
    
    questions = get_pending_questions()
    
    if not questions:
        current_question = None
        return jsonify({'new': False})

    if current_question is None or current_question not in questions:
        current_question = questions[0]
        return jsonify({'new': True, 'question': current_question})

    return jsonify({'new': False})

@app.route('/admin_chat', methods=['POST'])
def admin_chat():
    """Admin chatbot"""
    global current_question, faq_cache
    
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
            return jsonify({'reply': f"❓ {current_question}\n\nProvide answer:"})

        question = current_question
        answer = message

        # Save to FAQ in PARENT directory
        try:
            if FAQ_FILE.exists():
                faq = pd.read_csv(FAQ_FILE)
            else:
                faq = pd.DataFrame(columns=['question', 'answer'])

            faq = faq[faq['question'] != question]
            new_row = pd.DataFrame([{'question': question, 'answer': answer}])
            faq = pd.concat([faq, new_row], ignore_index=True)
            faq.to_csv(FAQ_FILE, index=False)
            
            print(f"✅ Saved to {FAQ_FILE}: {question} -> {answer}")
            
            # Invalidate cache
            faq_cache['data'] = None
            faq_cache['embeddings'] = None

        except Exception as e:
            return jsonify({'reply': f'Error: {str(e)}'}), 500

        remove_pending_question(question)

        current_question = None
        questions = get_pending_questions()

        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"✅ Saved!\n\nNext:\n❓ {next_q}\nProvide answer:"
        else:
            reply = "✅ Saved! No more pending."

        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Admin error: {e}")
        return jsonify({'reply': 'Error.'}), 500

@app.route('/health', methods=['GET'])
def health():
    """Health check"""
    faq_data, _ = load_faq_with_cache()
    faq_count = len(faq_data) if faq_data is not None else 0
    
    return jsonify({
        "status": "healthy",
        "version": "5.0-512MB-FINAL",
        "model": "all-MiniLM-L6-v2",
        "faq_file": str(FAQ_FILE),
        "faq_exists": FAQ_FILE.exists(),
        "faq_entries": faq_count,
        "faq_loaded": faq_data is not None
    }), 200

@app.route('/', methods=['GET'])
def root():
    """Root endpoint"""
    faq_data, _ = load_faq_with_cache()
    faq_count = len(faq_data) if faq_data is not None else 0
    
    return jsonify({
        "service": "Papsi Chatbot v5.0",
        "model": "all-MiniLM-L6-v2 (80MB)",
        "faq_location": "PARENT DIRECTORY",
        "faq_file": str(FAQ_FILE),
        "faq_entries": faq_count,
        "faq_status": "✅ Loaded" if faq_data is not None else "❌ Not Found",
        "features": [
            "FAQ-first priority",
            "512MB RAM optimized",
            "Semantic search (threshold: 0.50)",
            "Database service matching"
        ]
    }), 200

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    
    print(f"\n{'='*60}")
    print(f"🚀 Papsi Chatbot v5.0-512MB FINAL")
    print(f"{'='*60}")
    print(f"✅ Model: all-MiniLM-L6-v2 (80MB)")
    print(f"✅ FAQ: {FAQ_FILE}")
    print(f"✅ FAQ Exists: {FAQ_FILE.exists()}")
    print(f"✅ Port: {port}")
    print(f"{'='*60}\n")
    
    # Test FAQ on startup
    faq_data, faq_embeddings = load_faq_with_cache()
    if faq_data is not None:
        print(f"✅ FAQ READY: {len(faq_data)} entries\n")
    else:
        print(f"⚠️ FAQ NOT LOADED from {FAQ_FILE}\n")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
