"""
Papsi Repair Shop - Enhanced Lightweight Chatbot API
Using DistilBERT for semantic matching with FAQ and Database
Version: 4.0 - Lightweight + Accurate
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import os
import mysql.connector
from mysql.connector import pooling
from pathlib import Path
from dotenv import load_dotenv
import re
import traceback
import time
from datetime import datetime
import numpy as np
from sentence_transformers import SentenceTransformer
import torch
from sklearn.metrics.pairwise import cosine_similarity as sklearn_cosine_similarity

load_dotenv()
app = Flask(__name__)

CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "*").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type", "Authorization", "X-Customer-ID"]
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

# ==================== LIGHTWEIGHT TRANSFORMER MODEL ====================

print("🔄 Loading ultra-lightweight transformer model for 512MB RAM...")
try:
    # Using paraphrase-MiniLM-L3-v2: Ultra-small (61MB), fast, good enough accuracy
    # Better for 512MB RAM than all-MiniLM-L6-v2 (80MB)
    model = SentenceTransformer('paraphrase-MiniLM-L3-v2', device='cpu')
    
    # Force CPU mode for stability on low RAM
    model = model.to('cpu')
    model.eval()  # Set to evaluation mode for faster inference
    
    # Disable gradient computation for inference (saves memory)
    torch.set_grad_enabled(False)
    
    # Set low memory mode
    torch.set_num_threads(2)  # Limit CPU threads to save memory
    
    print("✅ Model loaded on CPU (ultra-low memory mode)")
    print(f"✅ Transformer model: paraphrase-MiniLM-L3-v2")
    print(f"   Model size: ~61MB, Embedding dimension: 384")
    print(f"   Memory optimized for 512MB RAM")
    print(f"   PyTorch version: {torch.__version__}")
    
except Exception as e:
    print(f"❌ Failed to load transformer model: {e}")
    traceback.print_exc()
    model = None

# ==================== CONNECTION POOL (512MB RAM OPTIMIZED) ====================
try:
    db_pool = pooling.MySQLConnectionPool(
        pool_name="chatbot_pool",
        pool_size=2,  # Reduced from 5 for 512MB RAM
        pool_reset_session=True,
        **DB_CONFIG
    )
    print("✅ Database connection pool initialized (2 connections - low memory mode)")
except Exception as e:
    print(f"❌ Failed to create connection pool: {e}")
    db_pool = None

# ==================== FILE PATHS ====================

CURRENT_DIR = Path(__file__).resolve().parent
ROOT_DIR = CURRENT_DIR.parent

FAQ_FILE = ROOT_DIR / 'faq.csv'
PENDING_FILE = CURRENT_DIR / 'pending_questions.csv'
ANSWERED_FILE = CURRENT_DIR / 'answered_questions.json'

print(f"📁 FAQ File: {FAQ_FILE}")
print(f"📁 Pending File: {PENDING_FILE}")

# ==================== INITIALIZE FILES ====================

def initialize_files():
    try:
        if FAQ_FILE.exists():
            faq_data = pd.read_csv(FAQ_FILE)
            print(f"✅ FAQ loaded: {len(faq_data)} entries")
        else:
            pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
            print("✅ Created new FAQ file")
            
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print("✅ Created pending questions file")
            
        if not Path(ANSWERED_FILE).exists():
            import json
            with open(ANSWERED_FILE, 'w') as f:
                json.dump({}, f)
            print("✅ Created answered tracking file")
        
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

# ==================== EMBEDDING CACHE (512MB RAM OPTIMIZED) ====================

# Minimal caching for low memory (512MB RAM)
faq_embeddings_cache = None
service_embeddings_cache = None
last_cache_update = 0
CACHE_TTL = 120  # 2 minutes (reduced from 5 for memory)
MAX_CACHED_FAQS = 50  # Limit FAQ cache size

def get_faq_embeddings(faq_data):
    """Get or compute FAQ embeddings with caching (512MB RAM optimized)"""
    global faq_embeddings_cache, last_cache_update
    
    current_time = time.time()
    
    # Return cached if available and fresh
    if faq_embeddings_cache is not None and (current_time - last_cache_update) < CACHE_TTL:
        return faq_embeddings_cache
    
    if model is None or len(faq_data) == 0:
        return None
    
    try:
        # Limit FAQ entries to save memory
        if len(faq_data) > MAX_CACHED_FAQS:
            print(f"⚠️ Limiting FAQ cache to {MAX_CACHED_FAQS} entries (RAM optimization)")
            faq_data = faq_data.head(MAX_CACHED_FAQS)
        
        questions = faq_data['question'].tolist()
        # Use convert_to_tensor=False for numpy arrays (faster on CPU, less memory)
        embeddings = model.encode(questions, convert_to_tensor=False, show_progress_bar=False, batch_size=8)
        faq_embeddings_cache = embeddings
        last_cache_update = current_time
        print(f"✅ Cached {len(questions)} FAQ embeddings (~{len(questions)*0.4:.1f}KB)")
        return embeddings
    except Exception as e:
        print(f"⚠️ Error computing FAQ embeddings: {e}")
        return None

def get_service_embeddings(services):
    """Compute service embeddings on-demand (NO CACHE for 512MB RAM)"""
    global service_embeddings_cache
    
    if model is None or len(services) == 0:
        return None
    
    # For 512MB RAM: NO CACHING - compute on demand
    # Service descriptions change rarely, but caching uses ~10-20KB per service
    try:
        # Combine service name and description for better matching
        service_texts = [
            f"{s['service_name']} {s['description']}" 
            for s in services
        ]
        # Use convert_to_tensor=False for numpy arrays, batch_size for memory
        embeddings = model.encode(service_texts, convert_to_tensor=False, show_progress_bar=False, batch_size=4)
        print(f"✅ Computed {len(services)} service embeddings on-demand")
        return embeddings
    except Exception as e:
        print(f"⚠️ Error computing service embeddings: {e}")
        return None

# ==================== DATABASE CONNECTION ====================

def get_db_connection():
    """Get connection from pool"""
    try:
        if db_pool:
            return db_pool.get_connection()
        else:
            return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"⚠️ DB connection error: {e}")
        return None

# ==================== CUSTOMER SESSION HANDLING ====================

def get_customer_from_request(request):
    """Extract customer information from request"""
    customer_id = None
    
    customer_id = request.headers.get('X-Customer-ID')
    
    if not customer_id:
        data = request.get_json()
        if data:
            customer_id = data.get('customer_id')
    
    if customer_id:
        return get_customer_details(customer_id)
    
    return None

def get_customer_details(customer_id):
    """Get customer details from database"""
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
            print(f"✅ Found customer: {customer['name']} ({customer['email']})")
        
        return customer
        
    except Exception as e:
        print(f"❌ Error fetching customer: {e}")
        return None
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

def get_customer_reservations(customer_id):
    """Get customer reservations"""
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            return []
        
        cursor = conn.cursor(dictionary=True)
        
        query = """
            SELECT 
                r.id,
                r.reservation_date,
                r.reservation_time,
                r.end_time,
                r.status,
                r.vehicle_make,
                r.vehicle_model,
                r.vehicle_year,
                r.method,
                r.created_at
            FROM reservations r
            WHERE r.customer_id = %s
            AND r.archived = 0
            ORDER BY r.reservation_date DESC, r.reservation_time DESC
        """
        
        cursor.execute(query, (customer_id,))
        reservations = cursor.fetchall()
        
        for reservation in reservations:
            reservation_id = reservation['id']
            
            service_query = """
                SELECT s.service_name, s.price
                FROM reservation_services rs
                JOIN services s ON rs.service_id = s.id
                WHERE rs.reservation_id = %s
            """
            
            cursor.execute(service_query, (reservation_id,))
            services = cursor.fetchall()
            
            if services:
                service_names = [f"{svc['service_name']} (₱{float(svc['price']):,.0f})" for svc in services]
                reservation['services'] = ", ".join(service_names)
            else:
                reservation['services'] = "No services listed"
        
        return reservations
        
    except Exception as e:
        print(f"❌ Reservation query error: {e}")
        traceback.print_exc()
        return []
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

def format_reservation_response(reservations, customer_name=None):
    """Format reservation data into readable response"""
    if not reservations:
        greeting = f"Hi {customer_name}! " if customer_name else ""
        return f"{greeting}You don't have any reservations yet. Would you like to schedule a service?"
    
    greeting = f"Hi {customer_name}! " if customer_name else ""
    
    upcoming = []
    completed = []
    pending = []
    cancelled = []
    
    today = datetime.now().date()
    
    for res in reservations:
        res_date = res['reservation_date']
        if isinstance(res_date, str):
            res_date = datetime.strptime(res_date, '%Y-%m-%d').date()
        
        if res['status'] == 'Completed':
            completed.append(res)
        elif res['status'] == 'Cancelled':
            cancelled.append(res)
        elif res['status'] == 'Pending':
            pending.append(res)
        elif res_date >= today:
            upcoming.append(res)
        else:
            completed.append(res)
    
    response = f"{greeting}Here are your reservations:\n\n"
    
    if upcoming:
        response += "📅 UPCOMING APPOINTMENTS:\n"
        for res in upcoming:
            response += format_single_reservation(res) + "\n"
    
    if pending:
        response += "\n⏳ PENDING CONFIRMATION:\n"
        for res in pending:
            response += format_single_reservation(res) + "\n"
    
    if completed:
        response += "\n✅ COMPLETED SERVICES:\n"
        for res in completed[:3]:  # Show only last 3 completed
            response += format_single_reservation(res) + "\n"
    
    return response.strip()

def format_single_reservation(res):
    """Format a single reservation"""
    res_date = res['reservation_date']
    if isinstance(res_date, str):
        res_date = datetime.strptime(res_date, '%Y-%m-%d').date()
    
    date_str = res_date.strftime('%B %d, %Y')
    time_str = str(res['reservation_time'])[:5] if res['reservation_time'] else 'N/A'
    end_time_str = str(res['end_time'])[:5] if res['end_time'] else 'N/A'
    
    status_emoji = {
        'Pending': '⏳',
        'Confirmed': '✅',
        'Completed': '✔️',
        'Cancelled': '❌'
    }.get(res['status'], '📌')
    
    text = f"  {status_emoji} Reservation #{res['id']} - {res['status']}\n"
    text += f"     📅 {date_str}\n"
    text += f"     🕐 {time_str} - {end_time_str}\n"
    
    if res['vehicle_make']:
        text += f"     🚗 {res['vehicle_year']} {res['vehicle_make']} {res['vehicle_model']}\n"
    
    if 'services' in res and res['services']:
        text += f"     🔧 Services: {res['services']}\n"
    
    return text

def is_reservation_query(message):
    """Check if message is asking about reservations"""
    reservation_keywords = [
        'reservation', 'reservations', 'booking', 'appointment', 'appointments',
        'schedule', 'status', 'when is my', 'what time', 'my appointment',
        'check my', 'view my', 'show my', 'upcoming', 'next appointment',
        'appointment status', 'booking status', 'schedule status'
    ]
    
    message_lower = message.lower()
    return any(keyword in message_lower for keyword in reservation_keywords)

# ==================== SEMANTIC FAQ SEARCH ====================

def semantic_faq_search(user_message, faq_data, threshold=0.65):
    """
    Use transformer embeddings to find best FAQ match
    Returns: (answer, confidence_score, matched_question)
    """
    if model is None or len(faq_data) == 0:
        return None, 0, None
    
    try:
        # Get FAQ embeddings (cached)
        faq_embeddings = get_faq_embeddings(faq_data)
        if faq_embeddings is None:
            return None, 0, None
        
        # Encode user message (batch_size=1 for memory optimization)
        user_embedding = model.encode(user_message, convert_to_tensor=False, show_progress_bar=False, batch_size=1)
        
        # Convert to numpy arrays for sklearn
        user_embedding_np = np.array([user_embedding])
        faq_embeddings_np = faq_embeddings
        
        # Compute cosine similarities using sklearn (faster on CPU)
        similarities = sklearn_cosine_similarity(user_embedding_np, faq_embeddings_np)[0]
        
        # Get best match
        best_idx = similarities.argmax()
        best_score = similarities[best_idx]
        
        if best_score >= threshold:
            answer = faq_data.iloc[best_idx]['answer']
            question = faq_data.iloc[best_idx]['question']
            print(f"🎯 FAQ Match: '{question}' (confidence: {best_score:.3f})")
            return answer, best_score, question
        else:
            print(f"❌ FAQ match below threshold: {best_score:.3f} < {threshold}")
            return None, best_score, None
            
    except Exception as e:
        print(f"⚠️ Semantic FAQ search error: {e}")
        traceback.print_exc()
        return None, 0, None

# ==================== SEMANTIC SERVICE MATCHING ====================

def semantic_service_matching(user_message, services, threshold=0.5):
    """
    Use transformer embeddings to match services
    Returns: list of (service, confidence_score)
    """
    if model is None or len(services) == 0:
        return []
    
    try:
        # Get service embeddings (computed on-demand for 512MB RAM)
        service_embeddings = get_service_embeddings(services)
        if service_embeddings is None:
            return []
        
        # Encode user message (batch_size=1 for memory optimization)
        user_embedding = model.encode(user_message, convert_to_tensor=False, show_progress_bar=False, batch_size=1)
        
        # Convert to numpy arrays for sklearn
        user_embedding_np = np.array([user_embedding])
        service_embeddings_np = service_embeddings
        
        # Compute cosine similarities using sklearn (faster on CPU, less memory)
        similarities = sklearn_cosine_similarity(user_embedding_np, service_embeddings_np)[0]
        
        # Get all matches above threshold
        matched_services = []
        for idx, score in enumerate(similarities):
            if score >= threshold:
                matched_services.append((services[idx], float(score)))
        
        # Sort by score
        matched_services.sort(key=lambda x: x[1], reverse=True)
        
        # Return top 3
        top_matches = matched_services[:3]
        
        if top_matches:
            print(f"🔧 Service Matches:")
            for service, score in top_matches:
                print(f"   - {service['service_name']}: {score:.3f}")
        
        return top_matches
        
    except Exception as e:
        print(f"⚠️ Semantic service matching error: {e}")
        traceback.print_exc()
        return []

# ==================== KEYWORD-BASED FALLBACK ====================

PROBLEM_KEYWORDS = {
    'engine': ['engine', 'motor', 'power', 'stall', 'misfire', 'overheating', 'noise', 'smoke'],
    'brake': ['brake', 'stop', 'squeak', 'grind', 'pedal', 'abs'],
    'oil': ['oil', 'change oil', 'lubricant', 'leak', 'filter'],
    'aircon': ['ac', 'aircon', 'air conditioning', 'cooling', 'cold', 'hot air', 'not cooling'],
    'electrical': ['battery', 'start', 'alternator', 'electrical', 'lights', 'wiring'],
    'body': ['body', 'dent', 'scratch', 'damage', 'panel'],
    'paint': ['paint', 'color', 'spray'],
    'wash': ['wash', 'clean', 'under']
}

def keyword_based_service_match(user_message, services):
    """Fallback: Match services using keywords"""
    message_lower = user_message.lower()
    matched_services = []
    
    for service in services:
        service_name = service['service_name'].lower()
        service_desc = service['description'].lower()
        
        # Check if any part of service name is in message
        name_words = service_name.split()
        for word in name_words:
            if len(word) > 3 and word in message_lower:
                matched_services.append((service, 0.7))
                break
        
        # Check problem keywords
        for problem_type, keywords in PROBLEM_KEYWORDS.items():
            if any(kw in message_lower for kw in keywords):
                if problem_type in service_name or problem_type in service_desc:
                    if (service, 0.7) not in matched_services:
                        matched_services.append((service, 0.6))
    
    return matched_services[:3]

# ==================== DATABASE OPERATIONS ====================

def get_services_from_db():
    """Get services from database"""
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            return []
            
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT service_name, description, duration, price FROM services WHERE is_archived = 0")
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

# ==================== PENDING QUESTIONS ====================

def save_pending_question(question):
    """Save pending question"""
    try:
        question_clean = question.strip()
        
        if PENDING_FILE.exists():
            pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        else:
            pending = pd.DataFrame(columns=['question'])
        
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].astype(str).str.strip()
        
        if question_clean in pending['question'].values:
            return False
        
        new_row = pd.DataFrame({'question': [question_clean]})
        pending = pd.concat([pending, new_row], ignore_index=True)
        pending.to_csv(PENDING_FILE, index=False)
        
        return True
        
    except Exception as e:
        print(f"❌ Error saving pending: {e}")
        return False

def get_pending_questions():
    """Get all pending questions"""
    try:
        if not PENDING_FILE.exists():
            return []
            
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        pending = pending.dropna(subset=['question'])
        pending['question'] = pending['question'].astype(str).str.strip()
        
        return pending['question'].tolist()
        
    except Exception as e:
        return []

def remove_pending_question(question):
    """Remove question from pending"""
    try:
        if not PENDING_FILE.exists():
            return
            
        pending = pd.read_csv(PENDING_FILE, dtype={'question': str})
        pending = pending[pending['question'] != question]
        pending.to_csv(PENDING_FILE, index=False)
        
    except Exception as e:
        print(f"❌ Error removing pending: {e}")

def save_answered_question(question, answer):
    """Track answered questions"""
    import json
    try:
        with open(ANSWERED_FILE, 'r') as f:
            answered = json.load(f)
        
        answered[question] = {
            'answer': answer,
            'timestamp': time.time()
        }
        
        current_time = time.time()
        answered = {
            q: data for q, data in answered.items()
            if current_time - data['timestamp'] < 3600
        }
        
        with open(ANSWERED_FILE, 'w') as f:
            json.dump(answered, f, indent=2)
        
    except Exception as e:
        print(f"❌ Error tracking answer: {e}")

def check_for_answer(question):
    """Check if question was answered"""
    import json
    try:
        with open(ANSWERED_FILE, 'r') as f:
            answered = json.load(f)
        
        if question in answered:
            answer_data = answered[question]
            
            del answered[question]
            with open(ANSWERED_FILE, 'w') as f:
                json.dump(answered, f, indent=2)
            
            return answer_data['answer']
        
        return None
        
    except Exception as e:
        return None

# ==================== MAIN CHAT ENDPOINT ====================

@app.route('/chat', methods=['POST'])
def chat():
    """Main chat endpoint with semantic matching"""
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Hi! How can I help you today?"})

        print(f"\n{'='*60}")
        print(f"📨 Message: '{user_message}'")
        
        # Get customer from session/request
        customer = get_customer_from_request(request)
        customer_name = customer['name'].split()[0] if customer else None
        
        print(f"👤 Customer: {customer_name if customer else 'Guest'}")
        print(f"{'='*60}")
        
        reply_parts = []
        
        # 1️⃣ RESERVATION QUERY
        if customer and is_reservation_query(user_message):
            print("🔍 Detected reservation query for logged-in customer")
            
            reservations = get_customer_reservations(customer['id'])
            reply = format_reservation_response(reservations, customer_name)
            
            return jsonify({
                'reply': reply,
                'type': 'reservation',
                'customer_name': customer_name
            })
        
        if not customer and is_reservation_query(user_message):
            return jsonify({
                'reply': "To view your reservations, please log in to your account first. 🔐"
            })
        
        # 2️⃣ Check for admin answer
        recent_answer = check_for_answer(user_message)
        if recent_answer:
            greeting = f"Hi {customer_name}! " if customer_name else ""
            return jsonify({
                'reply': f"{greeting}✅ Admin Response:\n\n{recent_answer}",
                'admin_answered': True
            })
        
        # 3️⃣ SEMANTIC FAQ SEARCH (using transformer)
        faq_reply = None
        try:
            if FAQ_FILE.exists():
                faq_data = pd.read_csv(FAQ_FILE)
                faq_data = faq_data.dropna(subset=['question', 'answer'])
                
                if len(faq_data) > 0:
                    faq_reply, faq_score, matched_q = semantic_faq_search(user_message, faq_data, threshold=0.65)
                    
                    if faq_reply:
                        greeting = f"Hi {customer_name}! " if customer_name else ""
                        reply_parts.append(f"{greeting}💡 {faq_reply}")
                        print(f"✅ FAQ match used (score: {faq_score:.2f})")
                    
        except Exception as e:
            print(f"⚠️ FAQ error: {e}")

        # 4️⃣ SEMANTIC SERVICE MATCHING (using transformer)
        try:
            services = get_services_from_db()
            
            if services and not faq_reply:
                # Try semantic matching first
                matched_services = semantic_service_matching(user_message, services, threshold=0.5)
                
                # Fallback to keyword matching if semantic fails
                if not matched_services:
                    matched_services = keyword_based_service_match(user_message, services)
                
                if matched_services:
                    greeting = f"Hi {customer_name}! " if customer_name and not reply_parts else ""
                    reply_parts.append(f"{greeting}🔧 Based on your concern, I recommend:\n")
                    
                    for service, score in matched_services:
                        part = (
                            f"• **{service['service_name']}**\n"
                            f"  📝 {service['description']}\n"
                            f"  🕒 Duration: {service['duration']}\n"
                            f"  💰 Price: ₱{float(service['price']):,.2f}\n"
                        )
                        reply_parts.append(part)
                    
                    reply_parts.append("\nWould you like to schedule an appointment for any of these services?")
                
        except Exception as e:
            print(f"⚠️ Service matching error: {e}")
            traceback.print_exc()

        # 5️⃣ Forward to admin if no matches
        if not reply_parts:
            saved = save_pending_question(user_message)
            
            if saved:
                greeting = f"Hi {customer_name}! " if customer_name else ""
                reply_parts.append(
                    f"{greeting}🤖 I'm not sure about that specific issue. "
                    "I've forwarded your question to our mechanic. "
                    "Check back soon for a detailed answer!"
                )

        reply = "\n".join(reply_parts)
        
        return jsonify({
            'reply': reply,
            'customer_name': customer_name
        })
        
    except Exception as e:
        print(f"💥 Chat error: {e}")
        traceback.print_exc()
        return jsonify({'reply': "Technical difficulties. Please try again."}), 500

# ==================== ADMIN ENDPOINTS ====================

current_question = None

@app.route('/pending', methods=['GET'])
def get_pending():
    """Get all pending questions"""
    questions = get_pending_questions()
    return jsonify(questions)

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """Get next pending question"""
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

        question = current_question
        answer = message

        try:
            if FAQ_FILE.exists():
                faq = pd.read_csv(FAQ_FILE)
            else:
                faq = pd.DataFrame(columns=['question', 'answer'])

            faq = faq[faq['question'].astype(str).str.strip() != question.strip()]
            
            new_data = {'question': question, 'answer': answer}
            new_row = pd.DataFrame([new_data])
            
            faq = pd.concat([faq, new_row], ignore_index=True)
            faq.to_csv(FAQ_FILE, index=False)
            
            # Invalidate FAQ cache to reload with new entry
            global faq_embeddings_cache
            faq_embeddings_cache = None

        except Exception as e:
            return jsonify({'reply': f'Error saving: {str(e)}'}), 500

        save_answered_question(question, answer)
        remove_pending_question(question)

        current_question = None
        questions = get_pending_questions()

        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"✅ Answer saved!\n\nNext:\n❓ {next_q}\nProvide answer:"
        else:
            reply = "✅ Answer saved! No more pending questions."

        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Admin error: {e}")
        traceback.print_exc()
        return jsonify({'reply': 'Error processing request.'}), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "service": "Ultra-Lightweight Papsi Chatbot (512MB RAM)",
        "version": "4.0-512MB",
        "model": "paraphrase-MiniLM-L3-v2",
        "model_size": "~61MB",
        "memory_mode": "Ultra-low (512MB RAM optimized)",
        "features": [
            "Semantic FAQ Search",
            "Semantic Service Matching", 
            "Customer Session Support",
            "512MB RAM Optimized"
        ]
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop - Ultra-Lightweight Semantic Chatbot",
        "version": "4.0-512MB",
        "model": "paraphrase-MiniLM-L3-v2 (DistilBERT)",
        "optimizations": [
            "✅ Ultra-lightweight transformer (61MB)",
            "✅ Optimized for 512MB RAM",
            "✅ Minimal FAQ caching (50 max)",
            "✅ On-demand service embeddings",
            "✅ Reduced connection pool (2 max)",
            "✅ Low memory batch processing",
            "✅ Fast inference on CPU",
            "✅ Customer session support"
        ]
    }), 200

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n{'='*60}")
    print(f"🚀 Ultra-Lightweight Papsi Chatbot v4.0-512MB - Port {port}")
    print(f"{'='*60}")
    print("✅ Model: paraphrase-MiniLM-L3-v2 (DistilBERT)")
    print("✅ Size: ~61MB (ultra-lightweight!)")
    print("✅ Memory: Optimized for 512MB RAM")
    print("✅ Speed: Fast CPU inference with minimal memory")
    print("✅ Features: Semantic matching for FAQ & Services")
    print("✅ Caching: FAQ only (2 min TTL, 50 max entries)")
    print("✅ Connection pool: 2 connections (low memory mode)")
    print(f"{'='*60}\n")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
