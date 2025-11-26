"""
Papsi Repair Shop - Complete Chatbot API 
OPTIMIZED FOR YOUR 3-COLUMN FAQ.CSV
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

# ==================== CORRECTED FILE PATHS ====================

CURRENT_DIR = Path(__file__).resolve().parent
ROOT_DIR = CURRENT_DIR.parent

# CORRECTED: FAQ is in the root directory
FAQ_FILE = ROOT_DIR / 'faq.csv'
PENDING_FILE = CURRENT_DIR / 'pending_questions.csv'

print(f"📁 Looking for FAQ at: {FAQ_FILE}")

# Initialize files with YOUR 3-COLUMN FAQ
def initialize_files():
    try:
        # Check if FAQ file exists and has content
        if FAQ_FILE.exists():
            faq_data = pd.read_csv(FAQ_FILE)
            print(f"✅ FOUND YOUR 3-COLUMN FAQ FILE: {len(faq_data)} entries")
            print(f"📊 FAQ Columns: {faq_data.columns.tolist()}")
            
            # Show what's in your FAQ
            print("📝 SAMPLE FAQ CONTENT:")
            for i, (idx, row) in enumerate(faq_data.iterrows()):
                if i < 3:  # Show first 3
                    print(f"   {i+1}. Q: {row['question']}")
                    print(f"      A: {row['answer'][:80]}...")
        else:
            print(f"❌ FAQ file not found at: {FAQ_FILE}")
            
        # Initialize pending file
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print("✅ Created pending questions file")
        
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

# ==================== OPTIMIZED FAQ SEARCH FOR YOUR DATA ====================

def preprocess_text(text):
    """Clean and preprocess text for better matching"""
    if not isinstance(text, str):
        return ""
    text = text.lower()
    text = re.sub(r'[^\w\s]', '', text)  # Remove punctuation
    return text

def smart_faq_search(user_message, faq_data):
    """
    Smart FAQ search optimized for your 3-column FAQ data
    """
    print(f"🔍 Searching FAQ for: '{user_message}'")
    
    user_clean = preprocess_text(user_message)
    user_words = set(user_clean.split())
    
    # Remove very short words but keep important ones
    user_words = {word for word in user_words if len(word) > 2}
    
    print(f"🔑 User words: {user_words}")
    
    if not user_words:
        return None, 0
        
    best_match = None
    best_score = 0
    best_question = None
    
    for idx, row in faq_data.iterrows():
        # Handle your 3-column format: question, answer, follow_up
        question = str(row['question'])
        answer = str(row['answer'])
        question_clean = preprocess_text(question)
        
        question_words = set(question_clean.split())
        common_words = user_words.intersection(question_words)
        
        if not common_words:
            continue
            
        # STRATEGY 1: Word overlap score
        score = len(common_words) / len(user_words)
        
        # STRATEGY 2: Exact phrase bonus (very important for your FAQ)
        if user_clean in question_clean:
            score += 1.0  # Higher bonus for exact matches
            print(f"  ✅ Exact phrase match!")
        
        # STRATEGY 3: Important auto repair keywords bonus
        important_auto_words = {
            'oil', 'change', 'brake', 'engine', 'start', 'battery', 'tire', 'wheel', 
            'ac', 'air', 'conditioning', 'transmission', 'suspension', 'check', 
            'light', 'noise', 'smoke', 'leak', 'overheating', 'fuel', 'consumption',
            'clutch', 'gear', 'shift', 'steering', 'power', 'loss', 'misfire',
            'coolant', 'exhaust', 'catalytic', 'converter', 'abs', 'pump', 'hybrid'
        }
        
        for word in common_words:
            if word in important_auto_words:
                score += 0.3
            elif len(word) > 4:
                score += 0.1
        
        # STRATEGY 4: Vehicle type matching
        vehicle_types = {'car', 'truck', 'van', 'motorcycle', 'motorbike', 'suv', 'lorry', 'pickup', 'diesel'}
        for vehicle in vehicle_types:
            if vehicle in user_clean and vehicle in question_clean:
                score += 0.2
        
        print(f"  📝 Checking: '{question}'")
        print(f"  🔄 Common words: {common_words}")
        print(f"  📊 Score: {score:.3f}")
        
        # VERY LOW THRESHOLD for better matching with your FAQ
        if score > best_score and score > 0.08:  # Lowered threshold
            best_score = score
            best_match = answer
            best_question = question
    
    if best_match:
        print(f"🎯 BEST MATCH FOUND: '{best_question}' -> score: {best_score:.3f}")
    else:
        print(f"❌ NO MATCH FOUND (best score: {best_score:.3f})")
        
    return best_match, best_score

def smart_service_search(user_message, services):
    """Smart service search with proper symptom-to-service mapping"""
    user_clean = preprocess_text(user_message)
    user_words = set(user_clean.split())
    
    # Remove very short words but keep important ones
    user_words = {word for word in user_words if len(word) > 2}
    
    print(f"🔧 Service search for: '{user_message}'")
    print(f"🔧 User words: {user_words}")
    
    if not user_words:
        return []
    
    # Enhanced symptom-to-service mapping
    symptom_service_map = {
        # Brake-related symptoms
        'brake': ['brake service', 'brake repair', 'brake fluid', 'brake bleeding'],
        'soft': ['brake service', 'brake bleeding', 'brake fluid'],
        'spongy': ['brake service', 'brake bleeding', 'brake fluid'],
        'pedal': ['brake service', 'brake repair'],
        'stop': ['brake service', 'brake repair'],
        'squeal': ['brake service', 'brake pad replacement'],
        'grind': ['brake service', 'brake pad replacement'],
        
        # Engine-related symptoms
        'engine': ['engine diagnostic', 'engine repair'],
        'noise': ['engine diagnostic', 'suspension repair'],
        'overheat': ['cooling system', 'engine diagnostic'],
        'misfire': ['engine diagnostic', 'ignition system'],
        'start': ['battery service', 'starter repair', 'electrical diagnostic'],
        
        # Electrical symptoms
        'battery': ['battery service', 'electrical diagnostic'],
        'light': ['electrical repair', 'bulb replacement'],
        'electrical': ['electrical diagnostic', 'wiring repair'],
        'power': ['electrical diagnostic', 'battery service'],
        
        # AC symptoms
        'ac': ['ac repair', 'ac service'],
        'air': ['ac repair', 'ac service'],
        'conditioning': ['ac repair', 'ac service'],
        'cool': ['ac repair', 'ac service'],
        'hot': ['ac repair', 'cooling system'],
        
        # Tire/Suspension symptoms
        'tire': ['tire service', 'wheel alignment'],
        'vibration': ['wheel alignment', 'tire balance', 'suspension repair'],
        'alignment': ['wheel alignment', 'suspension repair'],
        'steering': ['wheel alignment', 'suspension repair', 'power steering'],
        
        # Transmission symptoms
        'transmission': ['transmission service', 'transmission repair'],
        'gear': ['transmission service', 'clutch repair'],
        'shift': ['transmission service', 'clutch repair'],
    }
    
    matches = []
    
    for service in services:
        name = preprocess_text(service['service_name'])
        desc = preprocess_text(service['description'])
        
        score = 0
        
        # STRATEGY 1: Direct symptom-to-service mapping (HIGH PRIORITY)
        for symptom, service_list in symptom_service_map.items():
            if symptom in user_clean:
                # Check if this service matches any in the symptom mapping
                service_name_clean = preprocess_text(service['service_name'])
                for target_service in service_list:
                    if target_service in service_name_clean or target_service in desc:
                        score += 3.0  # High bonus for symptom-based matching
                        print(f"  ✅ Symptom '{symptom}' -> service '{service['service_name']}'")
        
        # STRATEGY 2: Exact keyword matching in service name (MEDIUM PRIORITY)
        for keyword in user_words:
            if keyword in name:
                score += 2.0
            elif keyword in desc:
                score += 1.0
        
        # STRATEGY 3: Category-based matching
        service_categories = {
            'brake': ['brake', 'pad', 'rotor', 'caliper', 'bleed'],
            'engine': ['engine', 'motor', 'cylinder', 'piston', 'valve'],
            'electrical': ['electrical', 'battery', 'wiring', 'light', 'sensor'],
            'ac': ['ac', 'air conditioning', 'cooling', 'compressor'],
            'transmission': ['transmission', 'gear', 'clutch', 'shift'],
            'suspension': ['suspension', 'shock', 'strut', 'alignment', 'steering']
        }
        
        for category, keywords in service_categories.items():
            # If user mentions category-related words
            if any(cat_word in user_clean for cat_word in [category] + keywords):
                # And service matches that category
                if any(cat_word in name for cat_word in keywords):
                    score += 2.0
                elif any(cat_word in desc for cat_word in keywords):
                    score += 1.5
        
        # STRATEGY 4: Exact phrase bonus
        if user_clean in name:
            score += 4.0
        elif user_clean in desc:
            score += 3.0
        
        # STRATEGY 5: Penalize completely irrelevant services
        irrelevant_combinations = [
            ('brake', 'ac'), ('brake', 'aircon'), ('brake', 'cleaning'),
            ('soft', 'ac'), ('pedal', 'ac'), ('stop', 'ac')
        ]
        
        for user_term, irrelevant_service in irrelevant_combinations:
            if user_term in user_clean and irrelevant_service in name:
                score = 0  # Completely exclude irrelevant matches
                print(f"  🚫 Excluded irrelevant: {service['service_name']}")
        
        print(f"  📊 Service: {service['service_name']} -> Score: {score:.2f}")
        
        # Only include services with meaningful matches
        if score >= 2.0:  # Increased threshold
            matches.append((service, score))
    
    # Return top 3 matches by score
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

# ==================== SSE ====================
clients = []

def send_sse_message(data):
    """Send SSE message to all connected clients"""
    for q in clients[:]:
        try:
            q.put(data, timeout=0.2)
        except Exception:
            clients.remove(q)

# ==================== ENDPOINTS ====================

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "faq_file_exists": FAQ_FILE.exists(),
        "faq_location": str(FAQ_FILE),
        "mode": "optimized-3column-faq"
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop Chatbot API",
        "status": "running", 
        "faq_format": "3-column (question, answer, follow_up)",
        "optimized": "for auto repair domain"
    }), 200

@app.route('/debug_faq', methods=['GET'])
def debug_faq():
    """Debug endpoint to check FAQ content"""
    try:
        if not FAQ_FILE.exists():
            return jsonify({"error": "FAQ file not found"}), 404
            
        faq_data = pd.read_csv(FAQ_FILE)
        
        return jsonify({
            "faq_file_exists": True,
            "faq_location": str(FAQ_FILE),
            "total_entries": len(faq_data),
            "columns": faq_data.columns.tolist(),
            "sample_questions": faq_data['question'].head(10).tolist(),
            "sample_answers_preview": [answer[:100] + "..." for answer in faq_data['answer'].head(10).tolist()]
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/test_search', methods=['POST'])
def test_search():
    """Test search for specific questions"""
    data = request.get_json()
    test_question = data.get('question', '').strip()
    
    if not test_question:
        return jsonify({"error": "Please provide a question"}), 400
    
    try:
        if not FAQ_FILE.exists():
            return jsonify({"error": "FAQ file not found"}), 404
            
        faq_data = pd.read_csv(FAQ_FILE)
        result, score = smart_faq_search(test_question, faq_data)
        
        return jsonify({
            "test_question": test_question,
            "found_match": result is not None,
            "answer": result,
            "confidence_score": score,
            "faq_entries_searched": len(faq_data)
        })
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/chat', methods=['POST'])
def chat():
    """Customer chatbot endpoint - OPTIMIZED FOR YOUR FAQ"""
    try:
        # Get and validate input
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message in JSON format."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Please describe your vehicle problem."})

        print(f"📨 Received: '{user_message}'")
        reply_parts = []
        
        # Check FAQ with OPTIMIZED search for your 3-column data
        try:
            if not FAQ_FILE.exists():
                print(f"❌ FAQ file not found at: {FAQ_FILE}")
                faq_reply = None
            else:
                # Read your 3-column FAQ
                faq_data = pd.read_csv(FAQ_FILE)
                
                # Clean data - handle your 3 columns
                faq_data = faq_data.dropna(subset=['question', 'answer'])
                faq_data = faq_data[faq_data['question'].str.strip() != '']
                faq_data = faq_data[faq_data['answer'].str.strip() != '']
                
                print(f"📊 Searching {len(faq_data)} FAQ entries...")
                
                faq_reply, score = smart_faq_search(user_message, faq_data)
                
                if faq_reply:
                    reply_parts.append(f"🔧 {faq_reply}")
                    print(f"✅ FAQ MATCH FOUND (score: {score:.3f})")
                else:
                    print(f"❌ NO FAQ MATCH (best score: {score:.3f})")
                    
        except Exception as e:
            print(f"⚠️ FAQ processing error: {e}")
            faq_reply = None

        # Check Services
        try:
            services = get_services_from_db()
            top_services = smart_service_search(user_message, services)
            
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

        # Add to FAQ (using your 3-column format)
        try:
            if FAQ_FILE.exists():
                faq = pd.read_csv(FAQ_FILE)
                # Remove existing entry if any
                faq = faq[faq['question'] != question]
                # Add new row with your 3-column format
                new_row = pd.DataFrame({
                    'question': [question], 
                    'answer': [answer],
                    'follow_up': ['']
                })
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
    print(f"\n🚀 Starting Papsi Chatbot on port {port}")
    print("💡 OPTIMIZED for your 3-column FAQ with auto repair content")
    print("🎯 Lower matching thresholds for better results")
    print("📊 Using smart keyword matching optimized for vehicle problems")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
