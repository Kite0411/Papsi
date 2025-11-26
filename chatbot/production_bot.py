"""
Papsi Repair Shop - Complete Chatbot API 
FIXED VERSION - Admin replies now saved correctly and customers see updates
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

# FAQ is in the root directory with 2 columns: question, answer
FAQ_FILE = ROOT_DIR / 'faq.csv'
PENDING_FILE = CURRENT_DIR / 'pending_questions.csv'
ANSWERED_FILE = CURRENT_DIR / 'answered_questions.json'  # NEW: Track answered questions

print(f"📁 FAQ File: {FAQ_FILE}")
print(f"📁 Pending File: {PENDING_FILE}")

# Initialize files
def initialize_files():
    try:
        # Check FAQ file
        if FAQ_FILE.exists():
            faq_data = pd.read_csv(FAQ_FILE)
            print(f"✅ FAQ loaded: {len(faq_data)} entries")
            print(f"📊 FAQ Columns: {faq_data.columns.tolist()}")
        else:
            # Create with 2 columns only
            pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
            print("✅ Created new FAQ file (2 columns)")
            
        # Initialize pending file
        if not PENDING_FILE.exists():
            pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
            print("✅ Created pending questions file")
            
        # Initialize answered tracking file
        if not Path(ANSWERED_FILE).exists():
            import json
            with open(ANSWERED_FILE, 'w') as f:
                json.dump({}, f)
            print("✅ Created answered tracking file")
        
    except Exception as e:
        print(f"❌ File initialization error: {e}")
        traceback.print_exc()

initialize_files()

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
            
        # Word overlap score
        score = len(common_words) / len(user_words)
        
        # Exact phrase bonus
        if user_clean in question_clean:
            score += 1.0
        
        # Important auto keywords bonus
        important_words = {
            'oil', 'change', 'brake', 'engine', 'start', 'battery', 'tire', 
            'ac', 'air', 'conditioning', 'transmission', 'suspension', 'check'
        }
        
        for word in common_words:
            if word in important_words:
                score += 0.3
        
        if score > best_score and score > 0.08:
            best_score = score
            best_match = answer
            best_question = question
    
    if best_match:
        print(f"🎯 Match found: '{best_question}' (score: {best_score:.3f})")
    
    return best_match, best_score

# ==================== DATABASE ====================

def get_db_connection():
    """Get database connection"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Exception as e:
        print(f"⚠️ DB error: {e}")
        return None

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

def smart_service_search(user_message, services):
    """Search for matching services"""
    user_clean = preprocess_text(user_message)
    user_words = [word for word in user_clean.split() if len(word) > 2]
    
    if not user_words:
        return []
        
    matches = []
    
    for service in services:
        name = preprocess_text(service['service_name'])
        desc = preprocess_text(service['description'])
        
        score = 0
        
        for keyword in user_words:
            if keyword in name:
                score += 2.0
            elif keyword in desc:
                score += 1.0
        
        if user_clean in name:
            score += 4.0
        elif user_clean in desc:
            score += 3.0
            
        if score > 0.3:
            matches.append((service, score))
    
    matches.sort(key=lambda x: x[1], reverse=True)
    return matches[:3]

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
        
        # Check duplicates
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

# ==================== ANSWERED QUESTIONS TRACKING ====================

def save_answered_question(question, answer):
    """Track answered questions for customer polling"""
    import json
    try:
        with open(ANSWERED_FILE, 'r') as f:
            answered = json.load(f)
        
        # Add new answer with timestamp
        answered[question] = {
            'answer': answer,
            'timestamp': time.time()
        }
        
        # Clean old answers (older than 1 hour)
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
    """Check if a question has been answered (for customer polling)"""
    import json
    try:
        with open(ANSWERED_FILE, 'r') as f:
            answered = json.load(f)
        
        if question in answered:
            answer_data = answered[question]
            
            # Remove from tracking after retrieval
            del answered[question]
            with open(ANSWERED_FILE, 'w') as f:
                json.dump(answered, f, indent=2)
            
            return answer_data['answer']
        
        return None
        
    except Exception as e:
        print(f"❌ Error checking answer: {e}")
        return None

# ==================== ENDPOINTS ====================

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        "status": "healthy",
        "faq_file": str(FAQ_FILE),
        "faq_exists": FAQ_FILE.exists()
    }), 200

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Papsi Repair Shop Chatbot API",
        "status": "running"
    }), 200

@app.route('/chat', methods=['POST'])
def chat():
    """Customer chatbot endpoint"""
    try:
        data = request.get_json()
        if not data:
            return jsonify({'reply': "Please provide a message."}), 400
            
        user_message = data.get('message', '').strip()
        if not user_message:
            return jsonify({'reply': "Please describe your vehicle problem."})

        print(f"📨 Customer: '{user_message}'")
        reply_parts = []
        
        # 1️⃣ Check if this question was recently answered by admin
        recent_answer = check_for_answer(user_message)
        if recent_answer:
            print(f"✅ Found recent admin answer!")
            return jsonify({
                'reply': f"✅ **Admin Response:**\n\n{recent_answer}",
                'admin_answered': True
            })
        
        # 2️⃣ Check FAQ
        faq_reply = None
        try:
            if FAQ_FILE.exists():
                faq_data = pd.read_csv(FAQ_FILE)
                faq_data = faq_data.dropna(subset=['question', 'answer'])
                
                faq_reply, score = smart_faq_search(user_message, faq_data)
                
                if faq_reply:
                    reply_parts.append(f"🔧 {faq_reply}")
                    print(f"✅ FAQ match (score: {score:.3f})")
                    
        except Exception as e:
            print(f"⚠️ FAQ error: {e}")

        # 3️⃣ Check Services
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
                print(f"✅ Found {len(top_services)} services")
                
        except Exception as e:
            print(f"⚠️ Service error: {e}")

        # 4️⃣ If no matches, forward to admin
        if not reply_parts:
            saved = save_pending_question(user_message)
            
            if saved:
                reply_parts.append(
                    "🤖 I'm not sure about that yet. I've forwarded your question to our admin. "
                    "Check back in a few moments for an update!"
                )
                print(f"📤 Forwarded to admin")

        reply = "\n\n".join(reply_parts)
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
    """Admin chatbot endpoint - FIXED TO SAVE CORRECTLY"""
    global current_question
    try:
        data = request.get_json()
        message = data.get('message', '').strip()

        if not message:
            return jsonify({'reply': 'Please type something.'})

        # If no active question, get first pending
        if current_question is None:
            questions = get_pending_questions()
            if not questions:
                return jsonify({'reply': '✅ No pending questions.'})
            current_question = questions[0]
            return jsonify({'reply': f"❓ {current_question}\n\nProvide your answer:"})

        # Save answer to FAQ
        question = current_question
        answer = message

        print(f"💾 Saving to FAQ: '{question}' -> '{answer}'")

        try:
            # Read existing FAQ
            if FAQ_FILE.exists():
                faq = pd.read_csv(FAQ_FILE)
                print(f"📊 Current FAQ has {len(faq)} entries")
            else:
                faq = pd.DataFrame(columns=['question', 'answer'])
            
            # Remove duplicate if exists
            faq = faq[faq['question'] != question]
            
            # Add new entry (2 COLUMNS ONLY: question, answer)
            new_row = pd.DataFrame({
                'question': [question], 
                'answer': [answer]
            })
            
            faq = pd.concat([faq, new_row], ignore_index=True)
            
            # Save to CSV
            faq.to_csv(FAQ_FILE, index=False)
            print(f"✅ SAVED TO FAQ: {question} -> {answer}")
            
            # Verify it was saved
            verify = pd.read_csv(FAQ_FILE)
            print(f"✅ Verified: FAQ now has {len(verify)} entries")
            
        except Exception as e:
            print(f"❌ FAQ SAVE ERROR: {e}")
            traceback.print_exc()

        # Track for customer polling
        save_answered_question(question, answer)

        # Remove from pending
        remove_pending_question(question)

        # Get next question
        current_question = None
        questions = get_pending_questions()

        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"✅ Answer saved!\n\nNext question:\n❓ {next_q}\nProvide your answer:"
        else:
            reply = "✅ Answer saved! No more pending questions."

        return jsonify({'reply': reply})
        
    except Exception as e:
        print(f"💥 Admin chat error: {e}")
        traceback.print_exc()
        return jsonify({'reply': 'Error processing request.'}), 500

@app.route('/check_answer/<path:question>', methods=['GET'])
def check_answer(question):
    """Endpoint for customer to check if their question was answered"""
    answer = check_for_answer(question)
    
    if answer:
        return jsonify({
            'answered': True,
            'answer': answer
        })
    else:
        return jsonify({'answered': False})

# ==================== RUN ====================

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    print(f"\n🚀 Starting Papsi Chatbot on port {port}")
    print("✅ Fixed: Admin replies now save correctly to FAQ")
    print("✅ Fixed: Customers can see admin replies via polling")
    
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
