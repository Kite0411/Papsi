from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import os
from pathlib import Path
import requests
app = Flask(__name__)
CORS(app)
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
CUSTOMER_URL = os.environ.get('CUSTOMER_NOTIFY_URL', 'http://127.0.0.1:5000/notify_customer')
# Ensure files exist
if not os.path.exists(FAQ_FILE):
    pd.DataFrame(columns=['question', 'answer']).to_csv(FAQ_FILE, index=False)
if not os.path.exists(PENDING_FILE):
    pd.DataFrame(columns=['question']).to_csv(PENDING_FILE, index=False)
current_question = None
@app.route('/admin', methods=['POST'])
def receive_from_customer():
    """Receives unknown questions from customer bot."""
    data = request.get_json()
    message = data.get('message', '')
    if 'New customer question:' in message:
        question = message.replace('New customer question:', '').strip()
        df = pd.read_csv(PENDING_FILE)
        if question not in df['question'].values:
            df = pd.concat([df, pd.DataFrame({'question': [question]})], ignore_index=True)
            df.to_csv(PENDING_FILE, index=False)
            print(f"📝 New pending question saved: {question}")
            return jsonify({'reply': 'Question saved for admin review.'})
    return jsonify({'reply': 'Invalid format.'})
@app.route('/get_next_question', methods=['GET'])
def get_next_question():
    """Check if there’s a new question waiting for the admin."""
    global current_question
    pending = pd.read_csv(PENDING_FILE)
    if pending.empty:
        return jsonify({'new': False})
    if current_question is None:
        current_question = pending.iloc[0]['question']
        return jsonify({'new': True, 'question': current_question})
    return jsonify({'new': False})
@app.route('/admin_chat', methods=['POST'])
def admin_chat():
    """Handles admin replies conversationally."""
    global current_question
    data = request.get_json()
    message = data.get('message', '').strip()
    if not message:
        return jsonify({'reply': 'Please type something.'})
    # Start a conversation if none is active
    if current_question is None:
        pending = pd.read_csv(PENDING_FILE)
        if pending.empty:
            return jsonify({'reply': '✅ No pending questions right now.'})
        current_question = pending.iloc[0]['question']
        return jsonify({'reply': f"❓ {current_question}\n\nPlease provide your answer:"})
    # Save answer to FAQ
    question = current_question
    answer = message
    faq = pd.read_csv(FAQ_FILE)
    # Remove duplicates + remove same question
    faq = faq.drop_duplicates(subset=['question'], keep='last')
    faq = faq[faq['question'] != question]
    # Insert new entry
    new_row = pd.DataFrame({'question': [question], 'answer': [answer]})
    faq = pd.concat([faq, new_row], ignore_index=True)
    faq.to_csv(FAQ_FILE, index=False)
    # Remove from pending
    pending = pd.read_csv(PENDING_FILE)
    pending = pending[pending['question'] != question]
    pending.to_csv(PENDING_FILE, index=False)
    print(f"✅ Saved to FAQ: {question} -> {answer}")
    # Notify customer bot immediately
    try:
        response = requests.post(CUSTOMER_URL, json={
            "question": question,
            "answer": answer
        })
        print(f"📨 Sent update to customer: {question} -> {answer}")
    except Exception as e:
        print(f"⚠️ Could not notify customer: {e}")
    # Reset and check for next question
    current_question = None
    pending = pd.read_csv(PENDING_FILE)
    if not pending.empty:
        next_q = pending.iloc[0]['question']
        current_question = next_q
        reply = f"✅ Answer saved and sent!\n\nNext question:\n❓ {next_q}\nPlease provide your answer:"
    else:
        reply = "✅ Answer saved and customer notified! No more pending questions."
    return jsonify({'reply': reply})
if __name__ == '__main__':
    app.run(host='127.0.0.1', port=7000)
