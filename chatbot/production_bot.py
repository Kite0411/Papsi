"""
Papsi Chatbot - DIAGNOSTIC VERSION
Shows detailed logs to debug FAQ matching issues
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

# ==================== CONFIG ====================

DB_CONFIG = {
    'host': os.environ.get('DB_HOST', '127.0.0.1'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'autorepair_db'),
    'port': int(os.environ.get('DB_PORT', 3306))
}

# ==================== MODEL ====================

print("🔄 Loading model...")
try:
    model = SentenceTransformer('all-MiniLM-L6-v2', device='cpu')
    model = model.to('cpu')
    model.eval()
    torch.set_grad_enabled(False)
    torch.set_num_threads(1)
    print("✅ Model loaded!")
except Exception as e:
    print(f"❌ Model failed: {e}")
    model = None

# ==================== DB POOL ====================

try:
    db_pool = pooling.MySQLConnectionPool(
        pool_name="chatbot_pool",
        pool_size=2,
        pool_reset_session=True,
        **DB_CONFIG
    )
    print("✅ DB pool ready")
except Exception as e:
    print(f"⚠️ DB pool failed: {e}")
    db_pool = None

# ==================== FILES ====================

CURRENT_DIR = Path(__file__).resolve().parent
ROOT_DIR = CURRENT_DIR.parent
FAQ_FILE = ROOT_DIR / 'faq.csv'
PENDING_FILE = CURRENT_DIR / 'pending_questions.csv'

print(f"\n{'='*70}")
print(f"📁 FAQ FILE: {FAQ_FILE}")
print(f"📁 EXISTS: {FAQ_FILE.exists()}")
print(f"{'='*70}\n")

# Initialize files
def initialize_files():
    try:
        if FAQ_FILE.exists():
            faq = pd.read_csv(FAQ_FILE)
            print(f"✅ FAQ: {len(faq)} entries")
            # Show first 5 questions
            print("\n📋 First 5 FAQ entries:")
            for i, row in faq.head(5).iterrows():
                q = row['question']
                print(f"   {i+1}. {q}")
        else:
            pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
            print(f"✅ Created FAQ at {FAQ_FILE}")
        
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print(f"✅ Created pending at {PENDING_FILE}")
    except Exception as e:
        print(f"❌ Init error: {e}")
        traceback.print_exc()

initialize_files()

# ==================== FAQ CACHE ====================

faq_cache = {
    'data': None,
    'embeddings': None,
    'last_update': 0,
    'file_mtime': 0
}

FAQ_CACHE_TTL = 300
MAX_FAQ_ENTRIES = 100

def load_faq_with_cache():
    global faq_cache
    
    try:
        if not FAQ_FILE.exists():
            print(f"❌ FAQ not found: {FAQ_FILE}")
            return None, None
        
        current_mtime = FAQ_FILE.stat().st_mtime
        current_time = time.time()
        
        # Check cache
        if (faq_cache['data'] is not None and 
            faq_cache['embeddings'] is not None and
            current_mtime == faq_cache['file_mtime'] and
            (current_time - faq_cache['last_update']) < FAQ_CACHE_TTL):
            print("✅ Using cached FAQ")
            return faq_cache['data'], faq_cache['embeddings']
        
        # Load fresh
        print(f"📂 Loading FAQ from: {FAQ_FILE}")
        faq_data = pd.read_csv(FAQ_FILE)
        
        print(f"📊 Raw FAQ: {len(faq_data)} rows")
        print(f"📊 Columns: {list(faq_data.columns)}")
        
        faq_data = faq_data.dropna(subset=['question', 'answer'])
        print(f"📊 After dropna: {len(faq_data)} rows")
        
        if len(faq_data) == 0:
            print("❌ FAQ is empty!")
            return None, None
        
        if len(faq_data) > MAX_FAQ_ENTRIES:
            faq_data = faq_data.head(MAX_FAQ_ENTRIES)
        
        # Generate embeddings
        embeddings = None
        if model is not None:
            try:
                print("🔄 Generating embeddings...")
                questions = faq_data['question'].tolist()
                print(f"   Questions to encode: {len(questions)}")
                
                embeddings = model.encode(
                    questions, 
                    convert_to_tensor=False, 
                    show_progress_bar=False, 
                    batch_size=8
                )
                print(f"✅ Embeddings shape: {embeddings.shape}")
            except Exception as e:
                print(f"❌ Embedding error: {e}")
                traceback.print_exc()
        
        # Cache it
        faq_cache['data'] = faq_data
        faq_cache['embeddings'] = embeddings
        faq_cache['last_update'] = current_time
        faq_cache['file_mtime'] = current_mtime
        
        return faq_data, embeddings
        
    except Exception as e:
        print(f"❌ Load FAQ error: {e}")
        traceback.print_exc()
        return None, None

# ==================== SEARCH FAQ (WITH DETAILED LOGGING) ====================

def search_faq_detailed(user_message, threshold=0.40):
    """Search with detailed logging"""
    try:
        print(f"\n{'='*70}")
        print(f"🔍 SEARCH FAQ: '{user_message}'")
        print(f"{'='*70}")
        
        faq_data, faq_embeddings = load_faq_with_cache()
        
        if faq_data is None:
            print("❌ No FAQ data loaded")
            return None, 0, None
        
        print(f"✅ FAQ data: {len(faq_data)} entries")
        print(f"✅ Embeddings: {'Yes' if faq_embeddings is not None else 'No'}")
        
        if faq_embeddings is None or model is None:
            print("⚠️ Using keyword fallback")
            return fallback_keyword_search(user_message, faq_data)
        
        # Encode user query
        print(f"🔄 Encoding query...")
        user_embedding = model.encode(user_message, convert_to_tensor=False)
        print(f"✅ Query embedding shape: {user_embedding.shape}")
        
        # Compute similarities
        user_emb_2d = np.array([user_embedding])
        similarities = np.dot(faq_embeddings, user_emb_2d.T).flatten()
        similarities = similarities / (
            np.linalg.norm(faq_embeddings, axis=1) * 
            np.linalg.norm(user_emb_2d)
        )
        
        print(f"\n📊 SIMILARITY SCORES:")
        print(f"   Min: {similarities.min():.3f}")
        print(f"   Max: {similarities.max():.3f}")
        print(f"   Mean: {similarities.mean():.3f}")
        print(f"   Threshold: {threshold}")
        
        # Get ALL matches above 0.3 for debugging
        print(f"\n🎯 ALL MATCHES ABOVE 0.30:")
        high_scores = [(idx, score) for idx, score in enumerate(similarities) if score > 0.30]
        high_scores.sort(key=lambda x: x[1], reverse=True)
        
        for idx, score in high_scores[:10]:  # Top 10
            q = faq_data.iloc[idx]['question']
            a = faq_data.iloc[idx]['answer']
            status = "✅" if score >= threshold else "⚠️"
            print(f"   {status} Score: {score:.3f} | Q: '{q[:60]}'")
            if score >= threshold:
                print(f"       A: {a[:80]}...")
        
        # Get best match
        best_idx = similarities.argmax()
        best_score = similarities[best_idx]
        
        print(f"\n🏆 BEST MATCH:")
        print(f"   Score: {best_score:.3f}")
        print(f"   Question: '{faq_data.iloc[best_idx]['question']}'")
        print(f"   Threshold: {threshold}")
        print(f"   Match: {'✅ YES' if best_score >= threshold else '❌ NO'}")
        print(f"{'='*70}\n")
        
        if best_score >= threshold:
            answer = faq_data.iloc[best_idx]['answer']
            question = faq_data.iloc[best_idx]['question']
            return answer, best_score, question
        else:
            return None, best_score, None
            
    except Exception as e:
        print(f"❌ Search error: {e}")
        traceback.print_exc()
        return None, 0, None

def fallback_keyword_search(user_message, faq_data):
    """Keyword fallback"""
    print("🔄 KEYWORD SEARCH:")
    user_lower = user_message.lower()
    
    best_match = None
    best_score = 0
    
    for idx, row in faq_data.iterrows():
        question = str(row['question']).lower()
        answer = str(row['answer'])
        
        user_words = set(user_lower.split())
        question_words = set(question.split())
        overlap = len(user_words & question_words)
        score = overlap / max(len(user_words), len(question_words), 1)
        
        if score > 0.2:  # Show any match > 0.2
            print(f"   Score: {score:.3f} | Q: '{question[:60]}'")
        
        if score > best_score:
            best_score = score
            best_match = (answer, score, row['question'])
    
    if best_score > 0.3:
        print(f"✅ Best keyword match: {best_score:.3f}")
        return best_match
    
    print(f"❌ No keyword match above 0.3")
    return None, 0, None

# ==================== DB FUNCTIONS ====================

def get_db_connection():
    try:
        if db_pool:
            return db_pool.get_connection()
        return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"⚠️ DB error: {e}")
        return None

def get_services_from_db():
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            return []
        
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT service_name, description, duration, price FROM services WHERE is_archived = 0")
        return cursor.fetchall()
    except Exception as e:
        print(f"⚠️ Service error: {e}")
        return []
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

def match_services_simple(user_message, services):
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

# ==================== PENDING ====================

def save_pending_question(question):
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
        return True
    except Exception as e:
        print(f"❌ Pending error: {e}")
        return False

def get_pending_questions():
    try:
        if not PENDING_FILE.exists():
            return []
        pending = pd.read_csv(PENDING_FILE)
        return pending['question'].tolist()
    except Exception as e:
        return []

def remove_pending_question(question):
    try:
        if not PENDING_FILE.exists():
            return
        pending = pd.read_csv(PENDING_FILE)
        pending = pending[pending['question'] != question]
        pending.to_csv(PENDING_FILE, index=False)
    except Exception as e:
        print(f"❌ Remove error: {e}")

# ==================== MAIN CHAT ====================

@app.route('/chat', methods=['POST'])
def chat():
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
        
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Hi! How can I help?"})
        
        print(f"\n{'='*70}")
        print(f"📨 USER MESSAGE: '{user_message}'")
        print(f"{'='*70}")
        
        # STEP 1: FAQ SEARCH (with 0.40 threshold instead of 0.50)
        faq_answer, faq_score, matched_q = search_faq_detailed(user_message, threshold=0.40)
        
        if faq_answer:
            print(f"✅ FAQ MATCH USED (score: {faq_score:.3f})")
            return jsonify({
                'reply': faq_answer,
                'type': 'faq',
                'confidence': faq_score,
                'matched_question': matched_q,
                'debug': f"Matched '{matched_q}' with score {faq_score:.3f}"
            })
        
        # STEP 2: SERVICES
        services = get_services_from_db()
        if services:
            matched_services = match_services_simple(user_message, services)
            if matched_services:
                reply_parts = ["🔧 Based on your concern:\n"]
                for service, score in matched_services:
                    reply_parts.append(
                        f"• **{service['service_name']}**\n"
                        f"  📝 {service['description']}\n"
                        f"  🕒 {service['duration']} | 💰 ₱{float(service['price']):,.2f}\n"
                    )
                reply_parts.append("\nWant to book?")
                return jsonify({'reply': "\n".join(reply_parts), 'type': 'service'})
        
        # STEP 3: FORWARD TO ADMIN
        save_pending_question(user_message)
        return jsonify({
            'reply': "🤖 I'm not sure about that. I've forwarded your question to our mechanic.",
            'type': 'pending'
        })
        
    except Exception as e:
        print(f"💥 Error: {e}")
        traceback.print_exc()
        return jsonify({'reply': "Technical error. Try again."}), 500

# ==================== ADMIN ENDPOINTS ====================

current_question = None

@app.route('/pending', methods=['GET'])
def get_pending():
    return jsonify(get_pending_questions())

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
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
    global current_question, faq_cache
    try:
        data = request.get_json()
        message = data.get('message', '').strip()
        
        if not message:
            return jsonify({'reply': 'Type something.'})
        
        if current_question is None:
            questions = get_pending_questions()
            if not questions:
                return jsonify({'reply': '✅ No pending.'})
            current_question = questions[0]
            return jsonify({'reply': f"❓ {current_question}\n\nAnswer:"})
        
        question = current_question
        answer = message
        
        # Save to FAQ
        if FAQ_FILE.exists():
            faq = pd.read_csv(FAQ_FILE)
        else:
            faq = pd.DataFrame(columns=['question', 'answer'])
        
        faq = faq[faq['question'] != question]
        new_row = pd.DataFrame([{'question': question, 'answer': answer}])
        faq = pd.concat([faq, new_row], ignore_index=True)
        faq.to_csv(FAQ_FILE, index=False)
        
        print(f"✅ Saved: {question} -> {answer}")
        
        # Invalidate cache
        faq_cache['data'] = None
        faq_cache['embeddings'] = None
        
        remove_pending_question(question)
        current_question = None
        questions = get_pending_questions()
        
        if questions:
            next_q = questions[0]
            current_question = next_q
            return jsonify({'reply': f"✅ Saved!\n\nNext:\n❓ {next_q}\nAnswer:"})
        else:
            return jsonify({'reply': "✅ Saved! No more pending."})
    except Exception as e:
        print(f"💥 Admin error: {e}")
        return jsonify({'reply': 'Error.'}), 500

@app.route('/health', methods=['GET'])
def health():
    faq_data, _ = load_faq_with_cache()
    return jsonify({
        "status": "healthy",
        "faq_file": str(FAQ_FILE),
        "faq_exists": FAQ_FILE.exists(),
        "faq_entries": len(faq_data) if faq_data is not None else 0,
        "faq_loaded": faq_data is not None,
        "model_loaded": model is not None
    }), 200

@app.route('/', methods=['GET'])
def root():
    faq_data, _ = load_faq_with_cache()
    return jsonify({
        "service": "Papsi Chatbot DIAGNOSTIC",
        "version": "DEBUG",
        "faq_file": str(FAQ_FILE),
        "faq_entries": len(faq_data) if faq_data is not None else 0,
        "threshold": "0.40 (lowered for testing)"
    }), 200

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    
    print(f"\n{'='*70}")
    print(f"🚀 DIAGNOSTIC VERSION - DETAILED LOGGING")
    print(f"{'='*70}")
    print(f"✅ FAQ: {FAQ_FILE}")
    print(f"✅ Port: {port}")
    print(f"✅ Threshold: 0.40 (lowered for testing)")
    print(f"{'='*70}\n")
    
    # Test load on startup
    faq_data, faq_embeddings = load_faq_with_cache()
    if faq_data is not None:
        print(f"✅ FAQ READY: {len(faq_data)} entries\n")
    else:
        print(f"⚠️ FAQ NOT LOADED\n")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
