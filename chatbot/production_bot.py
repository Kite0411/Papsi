"""
Papsi Repair Shop - Enhanced Chatbot API with Database Integration
IMPROVEMENTS:
1. Accurate problem diagnosis with better keyword matching
2. Reservation query support (status, dates, details)
3. Customer identification for personalized queries
4. Improved service recommendations
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

# ==================== PROBLEM DIAGNOSIS SYSTEM ====================

# Comprehensive problem keywords mapped to services
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
    """
    Accurately diagnose vehicle problem from user message
    Returns: list of (category, confidence_score) tuples
    """
    message_lower = user_message.lower()
    message_words = set(re.findall(r'\b\w+\b', message_lower))
    
    diagnoses = []
    
    for category, data in PROBLEM_CATEGORIES.items():
        score = 0
        matched_keywords = []
        
        # Check each keyword
        for keyword in data['keywords']:
            keyword_words = keyword.split()
            
            # Exact phrase match (higher score)
            if keyword in message_lower:
                score += 3.0
                matched_keywords.append(keyword)
            # Word overlap match
            elif any(word in message_words for word in keyword_words):
                score += 1.0
        
        if score > 0:
            # Normalize by keyword count for fair comparison
            confidence = score / len(data['keywords']) * 100
            diagnoses.append({
                'category': category,
                'confidence': confidence,
                'score': score,
                'priority': data['priority'],
                'matched_keywords': matched_keywords
            })
    
    # Sort by score and priority
    diagnoses.sort(key=lambda x: (x['score'], x['priority']), reverse=True)
    
    return diagnoses

def match_services_to_problem(diagnoses, services):
    """
    Match services to diagnosed problems
    Returns: list of (service, relevance_score) tuples
    """
    if not diagnoses:
        return []
    
    matched_services = []
    
    for diagnosis in diagnoses[:3]:  # Top 3 diagnoses
        category = diagnosis['category']
        category_data = PROBLEM_CATEGORIES[category]
        service_keywords = category_data['service_keywords']
        
        for service in services:
            score = 0
            service_name = service['service_name'].lower()
            service_desc = service['description'].lower()
            
            # Check if service matches this problem category
            for keyword in service_keywords:
                if keyword in service_name:
                    score += 5.0
                elif keyword in service_desc:
                    score += 2.0
            
            if score > 0:
                # Weight by problem confidence
                final_score = score * (diagnosis['confidence'] / 100)
                matched_services.append((service, final_score, category))
    
    # Remove duplicates and sort by score
    seen = set()
    unique_services = []
    for service, score, category in sorted(matched_services, key=lambda x: x[1], reverse=True):
        service_id = service['service_name']
        if service_id not in seen:
            seen.add(service_id)
            unique_services.append((service, score, category))
    
    return unique_services[:3]  # Return top 3

# ==================== RESERVATION QUERIES ====================

def extract_customer_identifier(message):
    """
    Try to extract customer identifier from message
    Could be: name, phone, email, reservation ID
    """
    identifiers = {}
    
    # Phone number pattern (Philippine format)
    phone_pattern = r'(\+?63|0)?[\s-]?9\d{2}[\s-]?\d{3}[\s-]?\d{4}'
    phone_match = re.search(phone_pattern, message)
    if phone_match:
        identifiers['phone'] = phone_match.group()
    
    # Email pattern
    email_pattern = r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b'
    email_match = re.search(email_pattern, message)
    if email_match:
        identifiers['email'] = email_match.group()
    
    # Reservation ID pattern (assuming numeric or alphanumeric)
    res_id_pattern = r'\b(?:reservation|booking|id)[:\s#]*(\d+)\b'
    res_id_match = re.search(res_id_pattern, message, re.IGNORECASE)
    if res_id_match:
        identifiers['reservation_id'] = res_id_match.group(1)
    
    return identifiers

def query_reservations(identifiers):
    """
    Query reservations from database based on identifiers
    """
    try:
        conn = get_db_connection()
        if not conn:
            return None
        
        cursor = conn.cursor(dictionary=True)
        
        # Build query based on available identifiers
        conditions = []
        params = []
        
        if 'reservation_id' in identifiers:
            conditions.append("r.id = %s")
            params.append(identifiers['reservation_id'])
        
        if 'phone' in identifiers:
            conditions.append("c.phone LIKE %s")
            params.append(f"%{identifiers['phone']}%")
        
        if 'email' in identifiers:
            conditions.append("c.email = %s")
            params.append(identifiers['email'])
        
        if not conditions:
            return None
        
        query = f"""
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
                c.first_name,
                c.last_name,
                c.email,
                c.phone
            FROM reservations r
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE {' OR '.join(conditions)}
            AND r.archived = 0
            ORDER BY r.reservation_date DESC
            LIMIT 5
        """
        
        cursor.execute(query, params)
        reservations = cursor.fetchall()
        cursor.close()
        conn.close()
        
        return reservations
        
    except Exception as e:
        print(f"❌ Reservation query error: {e}")
        traceback.print_exc()
        return None

def format_reservation_response(reservations):
    """
    Format reservation data into readable response
    """
    if not reservations:
        return "I couldn't find any reservations matching your information. Please provide your phone number, email, or reservation ID."
    
    response = f"📋 **I found {len(reservations)} reservation(s):**\n\n"
    
    for idx, res in enumerate(reservations, 1):
        res_date = res['reservation_date'].strftime('%B %d, %Y') if res['reservation_date'] else 'N/A'
        res_time = str(res['reservation_time']) if res['reservation_time'] else 'N/A'
        status_emoji = {
            'Pending': '⏳',
            'Confirmed': '✅',
            'Completed': '✔️',
            'Cancelled': '❌'
        }.get(res['status'], '📌')
        
        response += f"**{idx}. Reservation #{res['id']}**\n"
        response += f"{status_emoji} Status: **{res['status']}**\n"
        response += f"📅 Date: {res_date}\n"
        response += f"🕒 Time: {res_time}\n"
        
        if res['vehicle_make']:
            response += f"🚗 Vehicle: {res['vehicle_year']} {res['vehicle_make']} {res['vehicle_model']}\n"
        
        response += f"📞 Method: {res['method']}\n\n"
    
    return response.strip()

def is_reservation_query(message):
    """
    Check if message is asking about reservations
    """
    reservation_keywords = [
        'reservation', 'booking', 'appointment', 'schedule',
        'status', 'when', 'date', 'time', 'my appointment',
        'check my reservation', 'reservation status'
    ]
    
    message_lower = message.lower()
    return any(keyword in message_lower for keyword in reservation_keywords)

# ==================== TEXT PROCESSING ====================

def preprocess_text(text):
    """Clean and preprocess text"""
    if not isinstance(text, str):
        return ""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)
    return text

def smart_faq_search(user_message, faq_data):
    """Smart FAQ search with keyword matching"""
    print(f"🔍 Searching FAQ for: '{user_message}'")
    
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
        print(f"🎯 Match found: '{best_question}' (score: {best_score:.3f})")
    
    return best_match, best_score

# ==================== DATABASE QUERIES ====================

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
        
        print(f"✅ Loaded {len(services)} services")
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
            print(f"⏭️ Already pending: {question_clean}")
            return False
        
        new_row = pd.DataFrame({'question': [question_clean]})
        pending = pd.concat([pending, new_row], ignore_index=True)
        pending.to_csv(PENDING_FILE, index=False)
        
        print(f"✅ Saved to pending: {question_clean}")
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
        print(f"❌ Error reading pending: {e}")
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
        print(f"❌ Error removing pending: {e}")

def save_answered_question(question, answer):
    """Track answered questions for customer polling"""
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
        
        print(f"✅ Tracked answer for polling: {question}")
        
    except Exception as e:
        print(f"❌ Error tracking answer: {e}")

def check_for_answer(question):
    """Check if a question has been answered"""
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
        print(f"❌ Error checking answer: {e}")
        return None

# ==================== MAIN CHAT ENDPOINT ====================

@app.route('/chat', methods=['POST'])
def chat():
    """Enhanced customer chatbot endpoint with reservation queries"""
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Please describe your vehicle problem or ask about your reservation."})

        print(f"\n{'='*60}")
        print(f"📨 Customer: '{user_message}'")
        print(f"{'='*60}")
        
        reply_parts = []
        
        # 1️⃣ Check if this is a reservation query
        if is_reservation_query(user_message):
            print("🔍 Detected reservation query")
            
            identifiers = extract_customer_identifier(user_message)
            print(f"🆔 Identifiers found: {identifiers}")
            
            if identifiers:
                reservations = query_reservations(identifiers)
                if reservations:
                    reply = format_reservation_response(reservations)
                    return jsonify({'reply': reply, 'type': 'reservation'})
                else:
                    reply = ("I couldn't find any reservations with that information. "
                            "Please provide your phone number, email, or reservation ID.")
                    return jsonify({'reply': reply})
            else:
                reply = ("To check your reservation, please provide:\n"
                        "• Your phone number\n"
                        "• Your email address\n"
                        "• Or your reservation ID")
                return jsonify({'reply': reply})
        
        # 2️⃣ Check if admin recently answered this
        recent_answer = check_for_answer(user_message)
        if recent_answer:
            print(f"✅ Found recent admin answer!")
            return jsonify({
                'reply': f"✅ **Admin Response:**\n\n{recent_answer}",
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
                    reply_parts.append(f"💡 {faq_reply}")
                    print(f"✅ FAQ match (score: {score:.3f})")
                    
        except Exception as e:
            print(f"⚠️ FAQ error: {e}")

        # 4️⃣ Diagnose problem and recommend services
        try:
            services = get_services_from_db()
            
            # Diagnose the problem
            diagnoses = diagnose_problem(user_message)
            
            if diagnoses:
                print(f"\n🔬 DIAGNOSES:")
                for d in diagnoses[:3]:
                    print(f"  • {d['category'].upper()}: {d['confidence']:.1f}% (score: {d['score']})")
                    print(f"    Keywords: {', '.join(d['matched_keywords'][:5])}")
                
                # Match services to diagnosed problems
                matched_services = match_services_to_problem(diagnoses, services)
                
                if matched_services:
                    print(f"\n🛠️ RECOMMENDED SERVICES:")
                    
                    reply_parts.append("🔧 **Based on your problem, I recommend:**\n")
                    
                    for service, score, category in matched_services:
                        print(f"  • {service['service_name']} (score: {score:.2f}, category: {category})")
                        
                        part = (
                            f"**• {service['service_name']}**\n"
                            f"  📝 {service['description']}\n"
                            f"  🕒 Duration: {service['duration']}\n"
                            f"  💰 Price: ₱{float(service['price']):,.2f}\n"
                        )
                        reply_parts.append(part)
                    
                    reply_parts.append("\n💬 Would you like to schedule an appointment for any of these services?")
                
        except Exception as e:
            print(f"⚠️ Service matching error: {e}")
            traceback.print_exc()

        # 5️⃣ If no matches, forward to admin
        if not reply_parts:
            saved = save_pending_question(user_message)
            
            if saved:
                reply_parts.append(
                    "🤖 I'm not sure about that specific issue. I've forwarded your question to our mechanic. "
                    "Please check back in a few moments for a detailed answer!"
                )
                print(f"📤 Forwarded to admin")

        reply = "\n".join(reply_parts)
        print(f"\n✅ RESPONSE:\n{reply}\n{'='*60}\n")
        
        return jsonify({'reply': reply})
        
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
    """Get next pending question for admin"""
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

        print(f"💾 Saving to FAQ: '{question}' -> '{answer}'")

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
            
            print(f"💾 SAVED TO FAQ FILE: {FAQ_FILE}")

        except Exception as e:
            print(f"❌ FAQ SAVE ERROR: {e}")
            traceback.print_exc()
            return jsonify({'reply': f'Error saving to FAQ: {str(e)}'}), 500

        save_answered_question(question, answer)
        remove_pending_question(question)

        current_question = None
        questions = get_pending_questions()

        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"✅ Answer saved to FAQ!\n\nNext question:\n❓ {next_q}\nProvide your answer:"
        else:
            reply = "✅ Answer saved to FAQ! No more pending questions."

        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Admin chat error: {e}")
        traceback.print_exc()
        return jsonify({'reply': 'Error processing request.'}), 500

# ==================== HEALTH CHECK ====================

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "service": "Enhanced Papsi Chatbot",
        "version": "2.0",
        "faq_file": str(FAQ_FILE),
        "faq_exists": FAQ_FILE.exists()
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop - Enhanced Chatbot API",
        "status": "running",
        "features": [
            "Accurate problem diagnosis",
            "Reservation queries",
            "Service recommendations",
            "Admin Q&A system"
        ]
    }), 200

# ==================== RUN ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n{'='*60}")
    print(f"🚀 Starting Enhanced Papsi Chatbot on port {port}")
    print(f"{'='*60}")
    print("✅ Accurate problem diagnosis with keyword matching")
    print("✅ Reservation query support (status, dates)")
    print("✅ Database integration for personalized responses")
    print("✅ Improved service recommendations")
    print(f"{'='*60}\n")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
