"""
Papsi Repair Shop - Session-Aware Chatbot API
FEATURES:
1. Automatic customer recognition from session/token
2. Personalized responses based on logged-in customer
3. Direct reservation queries without asking credentials
4. Accurate problem diagnosis
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import os
import mysql.connector
from pathlib import Path
from dotenv import load_dotenv
import re
import traceback
import time
from datetime import datetime

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

# ==================== DATABASE CONNECTION ====================

def get_db_connection():
    """Get database connection"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Exception as e:
        print(f"⚠️ DB error: {e}")
        return None

# ==================== CUSTOMER SESSION HANDLING ====================

def get_customer_from_request(request):
    """
    Extract customer information from request
    Supports multiple authentication methods:
    1. X-Customer-ID header (preferred)
    2. customer_id in request body
    3. Authorization token (if you use JWT)
    """
    customer_id = None
    
    # Method 1: Check header
    customer_id = request.headers.get('X-Customer-ID')
    
    # Method 2: Check request body
    if not customer_id:
        data = request.get_json()
        if data:
            customer_id = data.get('customer_id')
    
    # Method 3: Check Authorization header (JWT example)
    # if not customer_id:
    #     auth_header = request.headers.get('Authorization')
    #     if auth_header:
    #         customer_id = decode_jwt_token(auth_header)
    
    if customer_id:
        print(f"👤 Customer ID from request: {customer_id}")
        return get_customer_details(customer_id)
    
    return None

def get_customer_details(customer_id):
    """
    Fetch customer details from database
    """
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
        cursor.close()
        conn.close()
        
        if customer:
            print(f"✅ Found customer: {customer['name']} ({customer['email']})")
        
        return customer
        
    except Exception as e:
        print(f"❌ Error fetching customer: {e}")
        return None

def get_customer_reservations(customer_id):
    """
    Get all reservations for a specific customer
    """
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
        cursor.close()
        conn.close()
        
        print(f"📋 Found {len(reservations)} reservations for customer {customer_id}")
        return reservations
        
    except Exception as e:
        print(f"❌ Reservation query error: {e}")
        traceback.print_exc()
        return []

def format_reservation_response(reservations, customer_name=None):
    """
    Format reservation data into readable response
    """
    if not reservations:
        greeting = f"Hi {customer_name}! " if customer_name else ""
        return f"{greeting}You don't have any reservations yet. Would you like to schedule a service?"
    
    greeting = f"Hi {customer_name}! " if customer_name else ""
    
    # Categorize reservations
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
    
    # Show upcoming first
    if upcoming:
        response += "📅 **UPCOMING APPOINTMENTS:**\n"
        for res in upcoming[:3]:
            response += format_single_reservation(res) + "\n"
    
    # Show pending
    if pending:
        response += "\n⏳ **PENDING CONFIRMATION:**\n"
        for res in pending[:2]:
            response += format_single_reservation(res) + "\n"
    
    # Show recent completed
    if completed and not upcoming:
        response += "\n✅ **RECENT SERVICES:**\n"
        for res in completed[:2]:
            response += format_single_reservation(res) + "\n"
    
    return response.strip()

def format_single_reservation(res):
    """Format a single reservation"""
    res_date = res['reservation_date']
    if isinstance(res_date, str):
        res_date = datetime.strptime(res_date, '%Y-%m-%d').date()
    
    date_str = res_date.strftime('%B %d, %Y')
    time_str = str(res['reservation_time'])[:5] if res['reservation_time'] else 'N/A'
    
    status_emoji = {
        'Pending': '⏳',
        'Confirmed': '✅',
        'Completed': '✔️',
        'Cancelled': '❌'
    }.get(res['status'], '📌')
    
    text = f"  {status_emoji} **Reservation #{res['id']}** - {res['status']}\n"
    text += f"     📅 {date_str} at {time_str}\n"
    
    if res['vehicle_make']:
        text += f"     🚗 {res['vehicle_year']} {res['vehicle_make']} {res['vehicle_model']}\n"
    
    return text

def is_reservation_query(message):
    """
    Check if message is asking about reservations
    """
    reservation_keywords = [
        'reservation', 'reservations', 'booking', 'appointment', 'appointments',
        'schedule', 'status', 'when is my', 'what time', 'my appointment',
        'check my', 'view my', 'show my', 'upcoming', 'next appointment',
        'appointment status', 'booking status', 'schedule status'
    ]
    
    message_lower = message.lower()
    return any(keyword in message_lower for keyword in reservation_keywords)

# ==================== PROBLEM DIAGNOSIS SYSTEM ====================

PROBLEM_CATEGORIES = {
    'engine': {
        'keywords': [
            'engine', 'motor', 'power', 'acceleration', 'rpm', 'rev', 'idle', 'stall',
            'rough', 'jerky', 'hesitation', 'misfire', 'backfire', 'smoke', 'exhaust',
            'check engine', 'cel', 'light on', 'knocking', 'rattling', 'ticking',
            'overheating', 'overheat', 'temperature', 'coolant', 'radiator',
            'shaking', 'vibrating', 'loss of power', 'weak acceleration'
        ],
        'service_keywords': ['engine', 'diagnostic', 'tune', 'repair'],
        'priority': 1
    },
    'brake': {
        'keywords': [
            'brake', 'braking', 'stop', 'stopping', 'pedal', 'squeak', 'squeal',
            'grind', 'grinding', 'soft', 'spongy', 'hard', 'stiff', 'vibration',
            'pulsing', 'pulse', 'abs', 'anti-lock', 'brake light', 'parking brake'
        ],
        'service_keywords': ['brake', 'pad', 'rotor', 'caliper'],
        'priority': 1
    },
    'air_conditioning': {
        'keywords': [
            'ac', 'air con', 'aircon', 'air conditioning', 'cool', 'cooling', 'cold',
            'hot', 'warm', 'temperature', 'climate', 'vent', 'blower', 'fan',
            'refrigerant', 'freon', 'condenser', 'compressor', 'not cold',
            'not cooling', 'no cold air', 'weak airflow'
        ],
        'service_keywords': ['air conditioning', 'ac', 'climate', 'cooling'],
        'priority': 2
    },
    'oil': {
        'keywords': [
            'oil', 'oil change', 'lubricant', 'filter', 'dirty oil', 'black oil',
            'oil leak', 'leaking oil', 'burning oil', 'oil consumption', 'oil level',
            'oil pressure', 'oil light', 'maintenance', 'service due'
        ],
        'service_keywords': ['oil', 'change', 'lubrication'],
        'priority': 2
    },
    'electrical': {
        'keywords': [
            'electrical', 'battery', 'dead', 'won\'t start', 'wont start', 'no start',
            'crank', 'cranking', 'alternator', 'charging', 'voltage', 'fuse',
            'relay', 'wiring', 'spark plug', 'ignition', 'starter', 'lights',
            'radio', 'power window', 'key fob'
        ],
        'service_keywords': ['electrical', 'battery', 'alternator', 'starter', 'diagnostic'],
        'priority': 1
    },
    'transmission': {
        'keywords': [
            'transmission', 'trans', 'gear', 'shift', 'shifting', 'clutch',
            'automatic', 'manual', 'neutral', 'reverse', 'slipping', 'jerking',
            'delay', 'hard shift', 'won\'t shift', 'transmission fluid'
        ],
        'service_keywords': ['transmission', 'gear', 'clutch'],
        'priority': 1
    },
    'suspension': {
        'keywords': [
            'suspension', 'shock', 'strut', 'spring', 'bounce', 'bumpy',
            'rough ride', 'handling', 'steering', 'wheel alignment', 'vibration',
            'shake', 'clunk', 'rattle', 'noise when turning'
        ],
        'service_keywords': ['suspension', 'shock', 'strut', 'alignment', 'wheel'],
        'priority': 2
    },
    'tire': {
        'keywords': [
            'tire', 'tyre', 'wheel', 'flat', 'puncture', 'pressure', 'tpms',
            'balance', 'rotation', 'alignment', 'uneven wear', 'wobble'
        ],
        'service_keywords': ['tire', 'wheel', 'alignment', 'balance', 'rotation'],
        'priority': 2
    }
}

def diagnose_problem(user_message):
    """Accurately diagnose vehicle problem"""
    message_lower = user_message.lower()
    message_words = set(re.findall(r'\b\w+\b', message_lower))
    
    diagnoses = []
    
    for category, data in PROBLEM_CATEGORIES.items():
        score = 0
        matched_keywords = []
        
        for keyword in data['keywords']:
            keyword_words = keyword.split()
            
            if keyword in message_lower:
                score += 3.0
                matched_keywords.append(keyword)
            elif any(word in message_words for word in keyword_words):
                score += 1.0
        
        if score > 0:
            confidence = score / len(data['keywords']) * 100
            diagnoses.append({
                'category': category,
                'confidence': confidence,
                'score': score,
                'priority': data['priority'],
                'matched_keywords': matched_keywords
            })
    
    diagnoses.sort(key=lambda x: (x['score'], x['priority']), reverse=True)
    return diagnoses

def match_services_to_problem(diagnoses, services):
    """Match services to diagnosed problems"""
    if not diagnoses:
        return []
    
    matched_services = []
    
    for diagnosis in diagnoses[:3]:
        category = diagnosis['category']
        category_data = PROBLEM_CATEGORIES[category]
        service_keywords = category_data['service_keywords']
        
        for service in services:
            score = 0
            service_name = service['service_name'].lower()
            service_desc = service['description'].lower()
            
            for keyword in service_keywords:
                if keyword in service_name:
                    score += 5.0
                elif keyword in service_desc:
                    score += 2.0
            
            if score > 0:
                final_score = score * (diagnosis['confidence'] / 100)
                matched_services.append((service, final_score, category))
    
    seen = set()
    unique_services = []
    for service, score, category in sorted(matched_services, key=lambda x: x[1], reverse=True):
        service_id = service['service_name']
        if service_id not in seen:
            seen.add(service_id)
            unique_services.append((service, score, category))
    
    return unique_services[:3]

# ==================== TEXT PROCESSING ====================

def preprocess_text(text):
    """Clean and preprocess text"""
    if not isinstance(text, str):
        return ""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)
    return text

def smart_faq_search(user_message, faq_data):
    """Smart FAQ search"""
    user_clean = preprocess_text(user_message)
    user_words = set(user_clean.split())
    user_words = {word for word in user_words if len(word) > 2}
    
    if not user_words:
        return None, 0
        
    best_match = None
    best_score = 0
    best_question = None
    
    for idx, row in faq_data.iterrows():
        question = str(row['question'])
        answer = str(row['answer'])
        question_clean = preprocess_text(question)
        
        question_words = set(question_clean.split())
        common_words = user_words.intersection(question_words)
        
        if not common_words:
            continue
            
        score = len(common_words) / len(user_words)
        
        if user_clean in question_clean:
            score += 1.0
        
        if score > best_score and score > 0.08:
            best_score = score
            best_match = answer
            best_question = question
    
    if best_match:
        print(f"🎯 FAQ match: '{best_question}' (score: {best_score:.3f})")
    
    return best_match, best_score

def get_services_from_db():
    """Get services from database"""
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
    """
    Main chat endpoint with session-aware customer recognition
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Hi! How can I help you today?"})

        print(f"\n{'='*60}")
        print(f"📨 Message: '{user_message}'")
        
        # 🔥 GET CUSTOMER FROM SESSION/REQUEST
        customer = get_customer_from_request(request)
        customer_name = customer['name'].split()[0] if customer else None
        
        print(f"👤 Customer: {customer_name if customer else 'Guest'}")
        print(f"{'='*60}")
        
        reply_parts = []
        
        # 1️⃣ RESERVATION QUERY (No need to ask for credentials!)
        if customer and is_reservation_query(user_message):
            print("🔍 Detected reservation query for logged-in customer")
            
            reservations = get_customer_reservations(customer['id'])
            reply = format_reservation_response(reservations, customer_name)
            
            return jsonify({
                'reply': reply,
                'type': 'reservation',
                'customer_name': customer_name
            })
        
        # If asking about reservations but NOT logged in
        if not customer and is_reservation_query(user_message):
            return jsonify({
                'reply': "To view your reservations, please log in to your account first. 🔐"
            })
        
        # 2️⃣ Check for admin answer
        recent_answer = check_for_answer(user_message)
        if recent_answer:
            greeting = f"Hi {customer_name}! " if customer_name else ""
            return jsonify({
                'reply': f"{greeting}✅ **Admin Response:**\n\n{recent_answer}",
                'admin_answered': True
            })
        
        # 3️⃣ Check FAQ
        faq_reply = None
        try:
            if FAQ_FILE.exists():
                faq_data = pd.read_csv(FAQ_FILE)
                faq_data = faq_data.dropna(subset=['question', 'answer'])
                
                faq_reply, score = smart_faq_search(user_message, faq_data)
                
                if faq_reply:
                    greeting = f"Hi {customer_name}! " if customer_name else ""
                    reply_parts.append(f"{greeting}💡 {faq_reply}")
                    
        except Exception as e:
            print(f"⚠️ FAQ error: {e}")

        # 4️⃣ Diagnose problem and recommend services
        try:
            services = get_services_from_db()
            diagnoses = diagnose_problem(user_message)
            
            if diagnoses:
                matched_services = match_services_to_problem(diagnoses, services)
                
                if matched_services:
                    greeting = f"Hi {customer_name}! " if customer_name and not reply_parts else ""
                    reply_parts.append(f"{greeting}🔧 **Based on your problem, I recommend:**\n")
                    
                    for service, score, category in matched_services:
                        part = (
                            f"**• {service['service_name']}**\n"
                            f"  📝 {service['description']}\n"
                            f"  🕒 Duration: {service['duration']}\n"
                            f"  💰 Price: ₱{float(service['price']):,.2f}\n"
                        )
                        reply_parts.append(part)
                    
                    reply_parts.append("\n💬 Would you like to schedule an appointment?")
                
        except Exception as e:
            print(f"⚠️ Service matching error: {e}")

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
        "service": "Session-Aware Papsi Chatbot",
        "version": "3.0"
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop - Session-Aware Chatbot",
        "features": [
            "Automatic customer recognition",
            "Session-based authentication",
            "Direct reservation queries",
            "Accurate problem diagnosis"
        ]
    }), 200

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n{'='*60}")
    print(f"🚀 Session-Aware Papsi Chatbot - Port {port}")
    print(f"{'='*60}")
    print("✅ Automatic customer recognition from session")
    print("✅ No need to ask for phone/email/ID")
    print("✅ Personalized greetings and responses")
    print(f"{'='*60}\n")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
