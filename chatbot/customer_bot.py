from flask import Flask, request, jsonify, Response
from flask_cors import CORS
import pandas as pd
from sentence_transformers import SentenceTransformer, util
import os
from pathlib import Path
import mysql.connector
from queue import Queue

app = Flask(__name__)
CORS(app)

# ---------- DATABASE CONNECTION ----------
def get_db_connection():
    return mysql.connector.connect(
        host=os.environ.get("DB_HOST", "127.0.0.1"),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_NAME", "autorepair_db"),
        port=int(os.environ.get("DB_PORT", 3306))
    )

# ---------- FILES ----------
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
    pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)

# ---------- EMBEDDING MODEL ----------
model = SentenceTransformer('paraphrase-MiniLM-L3-v2')
# ---------- SSE CLIENTS ----------
clients = []

def send_sse_message(data):
    for q in clients[:]:
        try:
            q.put(data, timeout=0.2)
        except Exception:
            clients.remove(q)

# ---------- NOTIFICATION ----------
@app.route('/notify_customer', methods=['POST'])
def notify_customer():
    data = request.get_json()
    question = data.get("question")
    answer = data.get("answer")

    if not question or not answer:
        return jsonify({"reply": "Invalid data"}), 400

    faq = pd.read_csv(FAQ_FILE)
    new_entry = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_entry], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)

    print(f"📩 Admin answered: {question} -> {answer}")
    send_sse_message(f"✅ Update from admin: {answer}")

    return jsonify({"reply": "Customer notified."})

# ---------- STREAM ENDPOINT ----------
@app.route('/stream')
def stream():
    q = Queue()
    clients.append(q)
    def event_stream(queue):
        while True:
            msg = queue.get()
            yield f"data: {msg}\n\n"
    return Response(event_stream(q), mimetype="text/event-stream")

# ---------- FETCH SERVICES ----------
def get_services_from_db():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT service_name, description, duration, price FROM services")
        services = cursor.fetchall()
        cursor.close()
        conn.close()
        return services
    except Exception as e:
        print("⚠️ Database error:", e)
        return []

# ---------- MAIN CHAT ----------
@app.route('/chat', methods=['POST'])
def chat():
    data = request.get_json()
    user_message = data.get('message', '').strip()

    if not user_message:
        return jsonify({'reply': "Please describe your vehicle problem so I can assist you."})

    # ---------- 1️⃣ Check FAQ (semantic) ----------
    faq_data = pd.read_csv(FAQ_FILE)
    faq_reply = None
    similarity_score = 0

    if len(faq_data) > 0:
        questions = faq_data['question'].tolist()
        answers = faq_data['answer'].tolist()
        faq_embeddings = model.encode(questions, convert_to_tensor=True)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        similarities = util.cos_sim(user_emb, faq_embeddings)
        index = int(similarities.argmax())
        similarity_score = float(similarities[0][index])
        if similarity_score >= 0.4:
            faq_reply = answers[index]

    # ---------- 2️⃣ Check Services (semantic) ----------
    services = get_services_from_db()
    top_services = []

    if services:
        text_data = [f"{s['service_name']} {s['description']}" for s in services]
        service_embeddings = model.encode(text_data, convert_to_tensor=True)
        user_emb = model.encode(user_message, convert_to_tensor=True)
        similarities = util.cos_sim(user_emb, service_embeddings)[0]

        scores = similarities.cpu().tolist()
        ranked = sorted(enumerate(scores), key=lambda x: x[1], reverse=True)
        for idx, score in ranked[:3]:
            if score > 0.25:
                top_services.append((services[idx], score))

    # ---------- 3️⃣ Build reply ----------
    reply_parts = []

    if faq_reply:
        reply_parts.append(f"🔧 {faq_reply}")

    if top_services:
        reply_parts.append("🧰 Based on your concern, here are some services you might need:")
        for s, score in top_services:
            part = (
                f"• **{s['service_name']}**\n"
                f"📝 {s['description']}\n"
                f"🕒 Duration: {s['duration']}\n"
                f"💰 Price: ₱{float(s['price']):,.2f}"
            )
            reply_parts.append(part)

    if not faq_reply and not top_services:
        # ---------- 4️⃣ Forward to Admin ----------
        pending = pd.read_csv(PENDING_FILE)
        if user_message not in pending['question'].values:
            new_row = pd.DataFrame({'question': [user_message]})
            pending = pd.concat([pending, new_row], ignore_index=True)
            pending.to_csv(PENDING_FILE, index=False)

        reply_parts.append(
            "🤖 I'm not sure about that yet. I've forwarded your question to our admin for review. "
            "You'll be updated here once the admin provides an answer."
        )

    reply = "\n\n".join(reply_parts)
    return jsonify({'reply': reply})


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, threaded=True)
