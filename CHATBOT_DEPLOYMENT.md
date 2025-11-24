# 🤖 Papsi Chatbot - Render Deployment Guide

## Overview

Your chatbot system has been **merged into a single Flask application** for deployment on Render.

```
┌─────────────────────────────────────────┐
│        HOSTINGER                        │
│  • Frontend (HTML/CSS/JS)               │
│  • PHP Backend                          │
│  • MySQL Database                       │
│  • chatbot/chat.php calls Render API ──┐│
└─────────────────────────────────────────┘│
                                           │
                HTTPS API Calls            │
                       ↓                   │
┌──────────────────────┴──────────────────┴┐
│        RENDER - production_bot.py        │
│  🤖 Customer Chatbot (AI-powered)        │
│  👨‍💼 Admin Chatbot (Q&A management)       │
│  📊 Connected to Hostinger MySQL         │
└──────────────────────────────────────────┘
```

---

## 🎯 What Was Merged

**Before:** Two separate bots
- `customer_bot.py` (port 5000)
- `admin_bot.py` (port 7000)
- `run_bots.py` to run both

**After:** One unified bot
- `production_bot.py` - Single Flask app with ALL endpoints
- Runs on ONE port (Render requirement)
- All features preserved

---

## 📡 API Endpoints

### Customer Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/chat` | POST | Main chatbot - AI-powered responses |
| `/stream` | GET | Real-time notifications (SSE) |

### Admin Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin_chat` | POST | Admin Q&A interface |
| `/get_next_question` | GET | Check for pending questions |
| `/pending` | GET | Get all pending questions |
| `/notify_customer` | POST | Send answer to customers |

### Utility Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check for Render |
| `/` | GET | API documentation |

---

## 🚀 Deployment Steps

### Step 1: Get Hostinger MySQL Remote Access

**IMPORTANT:** You need to enable remote MySQL access on Hostinger.

1. Login to Hostinger Control Panel: https://hpanel.hostinger.com/
2. Go to **Databases** → **Remote MySQL**
3. Add Render's IP ranges or use `%` (allow all)
4. Get your **remote MySQL hostname** (usually different from `localhost`)

**Example:**
- Local: `localhost`
- Remote: `sql123.hostinger.com` or similar

### Step 2: Update Environment Variables

Edit `render.yaml` (already done, but verify):

```yaml
- key: DB_HOST
  value: YOUR_HOSTINGER_MYSQL_HOST  # e.g., sql123.hostinger.com
- key: DB_USER
  value: u563434200_papsi
- key: DB_PASSWORD
  value: u563434200_@A
- key: DB_NAME
  value: u563434200_papsi
```

### Step 3: Deploy to Render

1. **Commit and push:**
   ```bash
   git add .
   git commit -m "Unified chatbot for Render deployment"
   git push origin main
   ```

2. **Go to Render Dashboard:** https://dashboard.render.com/

3. **Create New Web Service:**
   - Connect GitHub repo: `Kite0411/Papsi`
   - Render will auto-detect `render.yaml`

4. **Deploy!**
   - First build will take 10-15 minutes (downloading PyTorch ~2GB)
   - Watch the logs for errors

5. **Copy your Render URL:**
   - Example: `https://papsi-chatbot-api.onrender.com`

### Step 4: Update PHP to Use Render API

Edit `chatbot/chat.php` on Hostinger:

```php
// Instead of calling local Python bot, call Render API
$renderApiUrl = "https://your-render-service.onrender.com/chat";

$ch = curl_init($renderApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $userMessage]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo json_encode($result);
```

---

## 🔧 Features Included

### AI-Powered Chatbot
- ✅ **Sentence Transformers** - Semantic similarity search
- ✅ **PyTorch** - Machine learning backend
- ✅ **FAQ Matching** - Finds similar questions (40% threshold)
- ✅ **Service Recommendations** - Suggests relevant services

### Database Integration
- ✅ Connects to Hostinger MySQL remotely
- ✅ Fetches services from database
- ✅ Filters active services only

### Admin Features
- ✅ View pending questions
- ✅ Answer questions conversationally
- ✅ Auto-update FAQ
- ✅ Real-time customer notifications

### Real-time Notifications
- ✅ Server-Sent Events (SSE)
- ✅ Customers get instant updates when admin answers

---

## 📊 File Structure

```
chatbot/
├── production_bot.py      ← MAIN: Unified chatbot (deployed to Render)
├── customer_bot.py         ← OLD: Customer bot (keep for reference)
├── admin_bot.py            ← OLD: Admin bot (keep for reference)
├── app.py                  ← Simple version (not used)
├── faq.csv                 ← FAQ knowledge base
├── pending_questions.csv   ← Questions waiting for admin
└── render_api_helper.php   ← PHP helper to call Render API
```

---

## 🧪 Testing Your Deployment

### Test Health Endpoint
```bash
curl https://your-render-service.onrender.com/health
```

**Expected response:**
```json
{
  "status": "healthy",
  "service": "papsi-chatbot-api",
  "model_loaded": true,
  "database": "connected"
}
```

### Test Chat Endpoint
```bash
curl -X POST https://your-render-service.onrender.com/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "oil change"}'
```

**Expected response:**
```json
{
  "reply": "🧰 Based on your concern, here are some services you might need:\n• **Oil Change**\n..."
}
```

### Test Admin Endpoint
```bash
curl https://your-render-service.onrender.com/pending
```

**Expected response:**
```json
["How much is brake repair?", "Do you do engine diagnostics?"]
```

---

## ⚠️ Important Notes

### Render Free Tier Limitations
1. **Service Sleeps After 15 Minutes**
   - First request after sleep takes 30-60 seconds
   - Model needs to reload (adds 10-20 seconds)
   - Solution: Upgrade to paid tier or implement keep-alive

2. **Large Dependencies**
   - PyTorch: ~2GB
   - Sentence-transformers: ~500MB
   - Build time: 10-15 minutes
   - This is normal for AI models

3. **MySQL Connection**
   - Must use remote hostname (not localhost)
   - Enable remote access in Hostinger
   - Test connection before deploying

### Database Connection Issues

If database shows "disconnected" in health check:

1. **Verify Hostinger allows remote MySQL:**
   - Check Hostinger control panel → Databases → Remote MySQL
   - Add Render's IP or allow all (`%`)

2. **Get correct hostname:**
   - Not `localhost` - use the remote hostname from Hostinger
   - Usually like: `sql123.hostinger.com`

3. **Test connection locally:**
   ```python
   import mysql.connector
   conn = mysql.connector.connect(
       host="YOUR_REMOTE_HOST",
       user="u563434200_papsi",
       password="u563434200_@A",
       database="u563434200_papsi"
   )
   print("✅ Connected!")
   ```

---

## 🔄 Updating Your Chatbot

### To Update FAQ Knowledge
1. Admin answers questions via `/admin_chat`
2. Automatically saves to `faq.csv`
3. Changes persist across deployments (use persistent disk)

### To Deploy Code Changes
```bash
git add .
git commit -m "Update chatbot logic"
git push origin main
```
Render auto-deploys on git push!

---

## 📞 PHP Integration Example

`chatbot/chat.php` should call Render API:

```php
<?php
header('Content-Type: application/json');

// Get user message
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';

// Call Render API
$renderUrl = "https://your-render-service.onrender.com/chat";

$ch = curl_init($renderUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $userMessage]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds for AI processing

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo $response;  // Forward response to frontend
} else {
    echo json_encode(['reply' => 'Chatbot service temporarily unavailable']);
}
?>
```

---

## 📈 Monitoring

### Render Dashboard
- View logs: Dashboard → Your Service → Logs
- Check metrics: CPU, Memory usage
- Monitor uptime

### Common Log Messages
```
✅ AI model loaded successfully     ← Good!
⚠️ Database connection error        ← Check MySQL settings
🤖 Loading AI model...              ← Loading (takes 10-20s)
```

---

## 🎊 Success Checklist

- [ ] Hostinger MySQL remote access enabled
- [ ] Render service deployed successfully
- [ ] `/health` endpoint returns "healthy"
- [ ] Database shows "connected"
- [ ] Chat endpoint returns AI responses
- [ ] PHP updated to call Render API
- [ ] Tested chatbot on Hostinger website
- [ ] Admin can answer pending questions

---

**Last Updated:** November 24, 2025
**Chatbot Version:** 2.0.0 (Unified)
