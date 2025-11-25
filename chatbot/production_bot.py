"""
Papsi Repair Shop - FINAL FIXED & WORKING
Real-time admin + customer
No more indentation errors
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
import time
from filelock import FileLock

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

if not FAQ_FILE.exists():
    pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)

if not PENDING_FILE.exists():
    pd.DataFrame(columns=['question', 'timestamp']).to_csv(PENDING_FILE, index=False)

print(f"FAQ File: {FAQ_FILE}")
print(f"Pending File: {PENDING_FILE}")

FAQ_LOCK = FileLock(str(FAQ_FILE) + ".lock")
PENDING_LOCK = FileLock(str(PENDING_FILE) + ".lock")

# ==================== AI MODEL ====================
model = None
model_loading = False
model_loaded = False

def load_model_background():
    global model, model_loading, model_loaded
    if model_loaded or model_loading:
        return
    model_loading = True
    print("Loading AI model...")
    try:
        from sentence_transformers import SentenceTransformer
        model = SentenceTransformer('all-MiniLM-L6-v2')
        model_loaded = True
        print("AI model loaded")
    except Exception as e:
        print(f"Model error: {e}")
        model_loaded = False
    finally:
        model_loading = False

threading.Thread(target=load_model_background, daemon=True).start()

# ==================== SSE FOR REAL-TIME ====================
clients = []

def send_sse_message(data):
    dead_clients = []
    for q in clients[:]:
        try:
            q.put(data, timeout=0.2)
        except:
            dead_clients.append(q)
    for q in dead_clients:
        if q in clients:
            clients.remove(q)

# ==================== DATABASE ====================
def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as e:
        print(f"DB error: {e}")
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
        print(f"Service query error: {e}")
        return []

# ==================== SEMANTIC SEARCH (unchanged) ====================
# ... (your semantic_search_faq, semantic_search_services, keyword_search_services stay the same)
# I'm keeping them exactly as you had — only pasting critical part below

def semantic_search_faq(user_message, faq_data):
    if not model_loaded or model is None or len(faq_data) == 0:
        return None, 0
    try:
        from sentence_transformers import util
        faq_data = faq_data.dropna(subset=['question', 'answer'])
        faq_data = faq_data[faq_data['question'].str.strip() != '']
        faq_data = faq_data[faq_data['answer'].str.strip() != '']
        if len(faq_data) == 0:
            return None, 0
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        faq_embeddings = model.encode(questions, convert_to_tensor=True)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        similarities = util.cos_sim(user_emb, faq_embeddings)[0]
        user_keywords = set(user_message.lower().split())
        boosted_scores = []
        for i, question in enumerate(questions):
            score = float(similarities[i])
            question_keywords = set(question.lower().split())
            matches = len(user_keywords & question_keywords)
            if matches > 0:
                boost = min(matches * 0.1, 0.3)
                score += boost
            boosted_scores.append((i, score))
        best_idx, best_score = max(boosted_scores, key=lambda x: x[1])
        if best_score >= 0.25:
            return answers[best_idx], best_score
        return None, best_score
    except Exception as e:
        print(f"FAQ search error: {e}")
        return None, 0

def semantic_search_services(user_message, services):
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
        print(f"Service search error: {e}")
        return []

def keyword_search_services(user_message, services):
    keywords = user_message.lower().split()
    matches = []
    for service in services:
        name = service['service_name'].lower()
        desc = service['description'].lower()
        score = sum(1 for kw in keywords if kw in name or kw in desc)
        if score > 0:
            matches.append((service, score))
    matches.sort(key=lambda x: x[1], reverse=True)
    return matches[:3]

# ==================== PENDING QUESTIONS ====================
def save_pending_question(question):
    try:
        with PENDING_LOCK:
            if PENDING_FILE.exists():
                pending = pd.read_csv(PENDING_FILE, dtype={'question': str, 'timestamp': str})
            else:
                pending = pd.DataFrame(columns=['question', 'timestamp'])
            pending = pending.dropna(subset=['question'])
            pending['question'] = pending['question'].str.strip()
            pending = pending[pending['question'] != '']
            question_clean = question.strip()
            if question_clean in pending['question'].values:
                return False
            timestamp = str(int(time.time()))
            new_row = pd.DataFrame({'question': [question_clean], 'timestamp': [timestamp]})
            pending = pd.concat([pending, new_row], ignore_index=True)
            pending.to_csv(PENDING_FILE, index=False, encoding='utf-8')
            send_sse_message(json.dumps({'type': 'new_question', 'question': question_clean, 'timestamp': timestamp}))
            print(f"SAVED PENDING: {question_clean}")
            return True
    except Exception as e:
        print(f"ERROR saving pending: {e}")
        return False

def get_pending_questions():
    try:
        with PENDING_LOCK:
            if not PENDING_FILE.exists():
                return []
            pending = pd.read_csv(PENDING_FILE)
            pending = pending.dropna(subset=['question'])
            pending['question'] = pending['question'].str.strip()
            pending = pending[pending['question'] != '']
            if 'timestamp' in pending.columns:
                pending['timestamp'] = pd.to_numeric(pending['timestamp'], errors='coerce')
                pending = pending.sort_values('timestamp')
            return pending['question'].tolist()
    except Exception as e:
        print(f"ERROR reading pending: {e}")
        return []

def remove_pending_question(question):
    try:
        with PENDING_LOCK:
            if not PENDING_FILE.exists():
                return
            pending = pd.read_csv(PENDING_FILE)
            pending = pending[pending['question'] != question]
            pending.to_csv(PENDING_FILE, index=False)
            send_sse_message(json.dumps({'type': 'question_answered', 'question': question}))
    except Exception as e:
        print(f"Remove error: {e}")

# ==================== ENDPOINTS ====================
@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "healthy", "pending_count": len(get_pending_questions())})

@app.route('/', methods=['GET'])
def root():
    return jsonify({"service": "Papsi Chatbot API", "status": "running"})

@app.route('/chat', methods=['POST'])
def chat():
    data = request.get_json()
    user_message = data.get('message', '').strip()
    if not user_message:
        return jsonify({'reply': "Please describe your vehicle problem."})

    reply_parts = []

    # FAQ Check
    try:
        with FAQ_LOCK:
            faq_data = pd.read_csv(FAQ_FILE)
            faq_data = faq_data.dropna(subset=['question', 'answer'])
        faq_reply = None
        if model_loaded:
            faq_reply, _ = semantic_search_faq(user_message, faq_data)
    except:
        faq_reply = None

    # Services
    services = get_services_from_db()
    top_services = semantic_search_services(user_message, services) if model_loaded else keyword_search_services(user_message, services)

    if faq_reply:
        reply_parts.append(f"{faq_reply}")
    if top_services:
        reply_parts.append("Based on your concern, here are some services:")
        for s, _ in top_services:
            reply_parts.append(f"• **{s['service_name']}** - ₱{float(s['price']):,.2f}")

    if not faq_reply and not top_services:
        saved = save_pending_question(user_message)
        if saved:
            reply_parts.append("I've forwarded your question to our head mechanic. He'll reply to you directly in this chat very soon!")
        else:
            reply_parts.append("Your question is already with our mechanic. You'll get his reply here shortly!")

    reply = "\n\n".join(reply_parts)
    return jsonify({'reply': reply})

current_question = None
question_lock = threading.Lock()

@app.route('/pending', methods=['GET'])
def get_pending():
    return jsonify(get_pending_questions())

@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    global current_question
    with question_lock:
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
    global current_question
    data = request.get_json()
    message = data.get('message', '').strip()
    if not message:
        return jsonify({'reply': 'Please type something.'})

    with question_lock:
        if current_question is None:
            questions = get_pending_questions()
            if not questions:
                return jsonify({'reply': 'No pending questions.'})
            current_question = questions[0]
            return jsonify({'reply': f"{current_question}\n\nProvide your answer:"})

        question = current_question
        answer = message

        try:
            with FAQ_LOCK:
                faq = pd.read_csv(FAQ_FILE)
                faq = faq.drop_duplicates(subset=['question'], keep='last')
                faq = faq[faq['question'] != question]
                new_row = pd.DataFrame({'question': [question], 'answer': [answer]})
                faq = pd.concat([faq, new_row], ignore_index=True)
                faq.to_csv(FAQ_FILE, index=False)
            print(f"Saved to FAQ: {question} -> {answer}")
            send_sse_message(json.dumps({'type': 'answer_received', 'question': question, 'answer': answer}))
        except Exception as e:
            return jsonify({'reply': f'Error: {str(e)}'})

        remove_pending_question(question)
        current_question = None
        questions = get_pending_questions()
        if questions:
            next_q = questions[0]
            current_question = next_q
            reply = f"Answer saved!\n\nNext question:\n{next_q}\nProvide your answer:"
        else:
            reply = "Answer saved! No more pending questions."
        return jsonify({'reply': reply})

@app.route('/stream')
def stream():
    q = Queue()
    clients.append(q)
    def event_stream():
        try:
            while True:
                msg = q.get()
                yield f"data: {msg}\n\n"
        except GeneratorExit:
            if q in clients:
                clients.remove(q)
    return Response(event_stream(), mimetype="text/event-stream")

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False, threaded=True)
