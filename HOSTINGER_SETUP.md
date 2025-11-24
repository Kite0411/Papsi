# 🌐 Hostinger Setup Instructions

## ✅ Your Render API is Live!

Your Python chatbot is deployed on Render. Now you need to connect your Hostinger website to it.

---

## 📋 Step-by-Step Instructions

### **Step 1: Get Your Render API URL**

From your Render dashboard, copy your service URL. It should look like:
```
https://papsi-chatbot-api-XXXX.onrender.com
```

---

### **Step 2: Upload Files to Hostinger**

Upload these **2 files** to your Hostinger website:

1. **`chatbot/chat_proxy.php`** (NEW FILE - created for you)
2. **`chatbot/chatbot-ui.php`** (UPDATED - points to proxy)

**How to upload:**

#### Option A: File Manager
1. Login to Hostinger: https://hpanel.hostinger.com/
2. Go to **Files** → **File Manager**
3. Navigate to `public_html/chatbot/`
4. Upload both files (overwrite `chatbot-ui.php` if it exists)

#### Option B: FTP
1. Connect via FTP (FileZilla, WinSCP, etc.)
2. Navigate to `public_html/chatbot/`
3. Upload both files

---

### **Step 3: Configure Render API URL**

Edit `chatbot/chat_proxy.php` on Hostinger (line 30):

**Find this line:**
```php
$RENDER_API_URL = "https://papsi-chatbot-api.onrender.com/chat";
```

**Replace with YOUR actual Render URL:**
```php
$RENDER_API_URL = "https://your-actual-render-url.onrender.com/chat";
```

**Save the file!**

---

### **Step 4: Test Your Chatbot**

1. Go to your website: **https://blueviolet-seahorse-808517.hostingersite.com**
2. Open the chatbot widget (click the 💬 icon)
3. Type: **"I need an oil change"**
4. Wait 30-60 seconds for first response (if Render was sleeping)
5. You should get **AI-powered service recommendations!** 🎯

---

## 🔧 Troubleshooting

### **Error: "I'm having trouble connecting..."**

**Cause:** The `chat_proxy.php` file has wrong Render URL

**Fix:**
1. Check line 30 in `chat_proxy.php`
2. Make sure it has YOUR Render URL (copy from Render dashboard)
3. Make sure URL ends with `/chat`
4. Save and try again

---

### **First Request Takes 60 Seconds**

**This is NORMAL on Render free tier!**

- Service sleeps after 15 minutes
- First request "wakes it up" (30-60 seconds)
- Subsequent requests are fast (1-2 seconds)

**To fix:** Upgrade Render to paid plan for always-on service

---

### **Still Not Working?**

1. **Check Render is running:**
   - Go to Render dashboard
   - Check service status (should be "Live")

2. **Test Render API directly:**
   ```bash
   curl https://your-render-url.onrender.com/health
   ```
   Should return: `{"status": "healthy"}`

3. **Check chat_proxy.php uploaded correctly:**
   - Visit: `https://your-hostinger-site.com/chatbot/chat_proxy.php`
   - Should NOT show "404 Not Found"

4. **Check browser console:**
   - Open chatbot
   - Press F12 (Developer Tools)
   - Check Console tab for errors

---

## 📁 File Locations

```
Hostinger Website:
├── public_html/
    └── chatbot/
        ├── chat_proxy.php        ← NEW: PHP proxy to Render
        ├── chatbot-ui.php        ← UPDATED: Uses proxy
        ├── chat.php              ← OLD: Can keep or delete
        └── admin_chatbot.php     ← OLD: Can keep for reference
```

---

## 🎯 What Each File Does

| File | Purpose |
|------|---------|
| `chatbot-ui.php` | Frontend chatbot widget (JavaScript + CSS) |
| `chat_proxy.php` | PHP proxy that calls Render API |
| Render API | Python AI chatbot (handles the AI logic) |

**Flow:**
```
User types message
    ↓
chatbot-ui.php (JavaScript)
    ↓ POST to /chatbot/chat_proxy.php
chat_proxy.php (PHP)
    ↓ cURL to Render
Render API (Python AI)
    ↓ Query MySQL + AI processing
Response back to user
```

---

## ✅ Quick Checklist

- [ ] Render API is deployed and running
- [ ] Copied Render URL from dashboard
- [ ] Uploaded `chat_proxy.php` to Hostinger
- [ ] Uploaded updated `chatbot-ui.php` to Hostinger
- [ ] Edited `chat_proxy.php` line 30 with real Render URL
- [ ] Tested chatbot on website
- [ ] First request took 30-60s (normal)
- [ ] Subsequent requests are fast
- [ ] Getting AI-powered responses! 🎉

---

## 🚀 You're Done!

Your chatbot is now:
- ✅ Running AI on Render (Python)
- ✅ Connected to Hostinger website (PHP proxy)
- ✅ Using Hostinger MySQL database
- ✅ Giving smart service recommendations

**Enjoy your AI-powered chatbot!** 🤖✨
