from flask import Flask, request, jsonify
from flask_cors import CORS
import csv, os, json

app = Flask(__name__)

# Configure CORS for Hostinger frontend
CORS(app, resources={
    r"/*": {
        "origins": os.environ.get("CORS_ORIGINS", "https://blueviolet-seahorse-808517.hostingersite.com").split(","),
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type"]
    }
})

KNOWLEDGE_FILE = os.path.join(os.path.dirname(__file__), "knowledge.csv")
PENDING_FILE = os.path.join(os.path.dirname(__file__), "pending.json")

# --- Helper: load & save knowledge base ---
def load_knowledge():
    if not os.path.exists(KNOWLEDGE_FILE):
        return []
    with open(KNOWLEDGE_FILE, newline='', encoding='utf-8') as f:
        return list(csv.DictReader(f))

def save_knowledge(data):
    with open(KNOWLEDGE_FILE, "w", newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=["question", "answer"])
        writer.writeheader()
        writer.writerows(data)

# --- Helper: load & save pending ---
def load_pending():
    if not os.path.exists(PENDING_FILE):
        return []
    with open(PENDING_FILE, "r", encoding="utf-8") as f:
        return json.load(f)

def save_pending(data):
    with open(PENDING_FILE, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

# --- Customer chatbot route ---
@app.route("/chat", methods=["POST"])
def chat():
    message = request.json.get("message", "").strip().lower()
    knowledge = load_knowledge()

    # Search for answer in knowledge.csv
    for row in knowledge:
        if row["question"].lower() in message:
            return jsonify({"reply": row["answer"]})

    # If not found — save for admin
    pending = load_pending()
    pending.append({"question": message})
    save_pending(pending)

    return jsonify({"reply": "I'm not sure about that yet. I'll ask our mechanic and get back to you soon!"})

# --- Admin chatbot: fetch pending ---
@app.route("/pending", methods=["GET"])
def get_pending():
    return jsonify(load_pending())

# --- Admin chatbot: save new answer ---
@app.route("/save_answer", methods=["POST"])
def save_answer():
    data = request.json
    question = data.get("question", "").strip()
    answer = data.get("answer", "").strip()

    if not question or not answer:
        return jsonify({"status": "error", "message": "Question and answer required"}), 400

    # Save into knowledge.csv
    knowledge = load_knowledge()
    knowledge.append({"question": question, "answer": answer})
    save_knowledge(knowledge)

    # Remove from pending.json
    pending = [p for p in load_pending() if p["question"] != question]
    save_pending(pending)

    return jsonify({"status": "success", "message": "Answer saved to knowledge base!"})

# --- Health check endpoint for Render ---
@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "healthy", "service": "papsi-chatbot-api"}), 200

# --- Root endpoint ---
@app.route("/", methods=["GET"])
def root():
    return jsonify({
        "service": "Papsi Chatbot API",
        "version": "1.0.0",
        "endpoints": {
            "chat": "/chat (POST)",
            "pending": "/pending (GET)",
            "save_answer": "/save_answer (POST)",
            "health": "/health (GET)"
        }
    })

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    debug = os.environ.get("FLASK_ENV", "development") == "development"
    app.run(host="0.0.0.0", port=port, debug=debug)
