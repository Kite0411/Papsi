# ⚡ Quick Start - Deployment Setup

## 🎯 What You Have

**Architecture:**
```
┌─────────────────────────────────────────┐
│          HOSTINGER                      │
│  ┌────────────────────────────┐        │
│  │  Frontend (HTML/CSS/JS)    │        │
│  │  PHP Backend               │        │
│  │  MySQL Database            │        │
│  └────────────────────────────┘        │
│              ↓ API Call                 │
│              ↓                          │
└──────────────┼──────────────────────────┘
               ↓
               ↓ HTTPS
               ↓
┌──────────────┼──────────────────────────┐
│          RENDER                         │
│  ┌────────────────────────────┐        │
│  │  Flask Chatbot API         │        │
│  │  - /chat endpoint          │        │
│  │  - /pending endpoint       │        │
│  │  - /save_answer endpoint   │        │
│  └────────────────────────────┘        │
└─────────────────────────────────────────┘
```

---

## ✅ Step-by-Step Deployment

### 1️⃣ Deploy to Render (Python Flask API)

**Time: ~10 minutes**

1. **Push code to GitHub:**
   ```bash
   git add .
   git commit -m "Setup deployment architecture"
   git push origin main
   ```

2. **Create Render Service:**
   - Go to: https://dashboard.render.com/
   - Click **"New +"** → **"Web Service"**
   - Connect your repo: `Kite0411/Papsi`

3. **Configure:**
   | Field | Value |
   |-------|-------|
   | Name | `papsi-chatbot-api` |
   | Build Command | `pip install -r requirements.txt` |
   | Start Command | `gunicorn --bind 0.0.0.0:$PORT chatbot.app:app` |

4. **Environment Variables:**
   ```
   FLASK_ENV=production
   CORS_ORIGINS=https://blueviolet-seahorse-808517.hostingersite.com
   ```

5. **Deploy** → Wait 5-10 minutes → **Copy your Render URL**
   - Example: `https://papsi-chatbot-api.onrender.com`

6. **Test:**
   ```bash
   curl https://your-render-url/health
   ```
   Should return: `{"status": "healthy"}`

---

### 2️⃣ Update Configuration

**Time: ~2 minutes**

Edit `includes/config.php` and add:

```php
// Add this after database configuration
define('CHATBOT_API_URL', 'https://your-render-service.onrender.com');
```

---

### 3️⃣ Deploy to Hostinger (PHP + Frontend)

**Time: ~15 minutes**

#### Files to Upload:
✅ All `.php` files
✅ `assets/` folder
✅ `auth/` folder
✅ `admin/` folder
✅ `reservations/` folder
✅ `vendor/` folder
✅ `uploads/` folder
✅ `.htaccess`
✅ `autorepair_db.sql`

❌ `chatbot/` folder (Python - on Render)
❌ `.git/` folder
❌ `.env` files

#### Upload Methods:

**Option A: File Manager (Easier)**
1. Login: https://hpanel.hostinger.com/
2. **Files** → **File Manager**
3. Navigate to `public_html/`
4. Upload files

**Option B: FTP**
1. Get credentials: **Files** → **FTP Accounts**
2. Use FileZilla or WinSCP
3. Upload to `public_html/`

#### Import Database:
1. **Databases** → **phpMyAdmin**
2. Select: `u563434200_papsipaps`
3. **Import** → Choose `autorepair_db.sql`
4. Click **Go**

---

### 4️⃣ Test Everything

**Frontend Test:**
- Visit: https://blueviolet-seahorse-808517.hostingersite.com
- Check login works
- Navigate admin panel

**Chatbot Test:**
- Open chatbot on your website
- Send a message
- Should get response from Render API

**API Test:**
```bash
curl -X POST https://your-render-url/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "hello"}'
```

---

## 🔧 Configuration Files Reference

### Files Created:

| File | Purpose |
|------|---------|
| `render.yaml` | Render deployment config |
| `.htaccess` | Hostinger security & routing |
| `.env.example` | Environment variables template |
| `.gitignore` | Git ignore rules |
| `config.production.php` | Production PHP config |
| `chatbot/render_api_helper.php` | PHP helper for Render API |
| `DEPLOYMENT_GUIDE.md` | Full deployment guide |

---

## 🚨 Important URLs

### Render:
- **Dashboard:** https://dashboard.render.com/
- **Your API:** Copy after deployment
- **Logs:** Render Dashboard → Your Service → Logs

### Hostinger:
- **Control Panel:** https://hpanel.hostinger.com/
- **Your Site:** https://blueviolet-seahorse-808517.hostingersite.com
- **phpMyAdmin:** Via control panel

---

## 🐛 Quick Troubleshooting

### Chatbot not working?
1. Check `CHATBOT_API_URL` in `config.php`
2. Test Render API: `curl https://your-render-url/health`
3. Check browser console for CORS errors
4. Verify Render logs

### 500 Error on Hostinger?
1. Check `logs/php-errors.log`
2. Verify database connection
3. Test `.htaccess` syntax

### Database issues?
1. Verify credentials in `config.php`
2. Re-import `autorepair_db.sql`
3. Check phpMyAdmin connection

---

## ✨ Next Steps

After successful deployment:

1. **Monitor Logs:**
   - Render: Dashboard → Logs
   - Hostinger: `logs/php-errors.log`

2. **Set Production Mode:**
   ```php
   define('DEBUG_MODE', false);
   ```

3. **Test All Features:**
   - [ ] Login/Signup
   - [ ] Reservations
   - [ ] Chatbot
   - [ ] Admin panel
   - [ ] Email notifications

4. **Setup Backups:**
   - Database: Weekly exports
   - Files: Regular backups

---

## 📚 Full Documentation

For complete details, see `DEPLOYMENT_GUIDE.md`

---

**Need Help?**
- Check `DEPLOYMENT_GUIDE.md` for detailed troubleshooting
- Review Render logs for API errors
- Check Hostinger error logs for PHP issues

**Ready to deploy? Start with Step 1! 🚀**
