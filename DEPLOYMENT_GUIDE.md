# 🚀 Papsi Repair Shop - Deployment Guide

## Architecture Overview

Your application is split into two parts:

1. **Hostinger** - Frontend + PHP Backend
   - Main website (HTML/CSS/JS)
   - PHP backend for reservations, authentication, admin panel
   - MySQL database

2. **Render** - Python Flask Chatbot API
   - AI-powered chatbot service
   - Handles chat queries and knowledge base
   - Scalable microservice architecture

---

## 📦 Part 1: Deploy Flask Chatbot API to Render

### Step 1: Prepare Your Repository

1. Ensure all changes are committed to your git repository:
   ```bash
   git add .
   git commit -m "Setup deployment architecture for Hostinger and Render"
   git push origin main
   ```

### Step 2: Create Render Service

1. Go to [Render Dashboard](https://dashboard.render.com/)
2. Click **"New +"** → **"Web Service"**
3. Connect your GitHub repository (Kite0411/Papsi)

### Step 3: Configure Render Service

Use these settings:

| Setting | Value |
|---------|-------|
| **Name** | `papsi-chatbot-api` |
| **Region** | Choose closest to Philippines (e.g., Singapore) |
| **Branch** | `main` (or your deployment branch) |
| **Root Directory** | Leave blank |
| **Runtime** | Python 3 |
| **Build Command** | `pip install -r requirements.txt` |
| **Start Command** | `gunicorn --bind 0.0.0.0:$PORT chatbot.app:app` |

### Step 4: Set Environment Variables in Render

Add these environment variables:

| Key | Value |
|-----|-------|
| `PYTHON_VERSION` | `3.11.0` |
| `FLASK_ENV` | `production` |
| `CORS_ORIGINS` | `https://blueviolet-seahorse-808517.hostingersite.com` |

### Step 5: Deploy

1. Click **"Create Web Service"**
2. Wait for deployment to complete (5-10 minutes)
3. **Copy your Render URL** (e.g., `https://papsi-chatbot-api.onrender.com`)

### Step 6: Test Your API

Test the endpoints:

```bash
# Health check
curl https://your-render-service.onrender.com/health

# Test chat endpoint
curl -X POST https://your-render-service.onrender.com/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "hello"}'
```

---

## 🌐 Part 2: Deploy Frontend + PHP Backend to Hostinger

### Step 1: Prepare Files for Upload

**Files to upload to Hostinger:**
- All PHP files (`*.php`)
- `assets/` directory
- `auth/` directory
- `admin/` directory
- `reservations/` directory
- `vendor/` directory (Composer dependencies)
- `uploads/` directory
- `.htaccess` file
- `autorepair_db.sql` (for database setup)

**Files NOT to upload:**
- `chatbot/` directory (Python files - these are on Render)
- `requirements.txt`
- `run_bots.py`
- `.git/` directory
- `.env` files

### Step 2: Update Configuration

1. **Update `includes/config.php`:**

   Find this line (around line 36):
   ```php
   define('APP_URL', 'https://blueviolet-seahorse-808517.hostingersite.com');
   ```

   Add this line after the database configuration:
   ```php
   // Chatbot API URL - Your Render service URL
   define('CHATBOT_API_URL', 'https://papsi-chatbot-api.onrender.com');
   ```

2. **Verify database credentials** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'u563434200_papsipaps');
   define('DB_PASSWORD', 'u563434200_@A');
   define('DB_NAME', 'u563434200_papsipaps');
   ```

### Step 3: Upload to Hostinger

#### Option A: Using File Manager (Recommended for beginners)

1. Log in to [Hostinger Control Panel](https://hpanel.hostinger.com/)
2. Go to **Files** → **File Manager**
3. Navigate to `public_html/` directory
4. Upload all files (except those listed in "Files NOT to upload")
5. Ensure `.htaccess` is uploaded and visible

#### Option B: Using FTP

1. Get FTP credentials from Hostinger:
   - Go to **Files** → **FTP Accounts**
   - Use provided hostname, username, password

2. Connect using FTP client (FileZilla, WinSCP):
   ```
   Host: ftp.yourdomain.com
   Username: your_ftp_username
   Password: your_ftp_password
   Port: 21
   ```

3. Upload files to `public_html/` directory

### Step 4: Setup Database

1. In Hostinger control panel, go to **Databases** → **phpMyAdmin**
2. Select your database: `u563434200_papsipaps`
3. Click **Import** tab
4. Choose `autorepair_db.sql` file
5. Click **Go** to import

### Step 5: Set File Permissions

Ensure these directories are writable (755 or 775):
- `uploads/`
- `logs/`
- `vendor/`

### Step 6: Test Your Website

1. Visit your website: `https://blueviolet-seahorse-808517.hostingersite.com`
2. Test login functionality
3. Test chatbot (should now connect to Render API)
4. Check admin panel

---

## 🔗 Integrating PHP Frontend with Render API

### Example: Calling Render Chatbot API from PHP

Add this to your `config.php` or create a new helper file:

```php
/**
 * Call the Flask chatbot API on Render
 * @param string $message - User's message
 * @return array - Chatbot response
 */
function callRenderChatbot($message) {
    $url = CHATBOT_API_URL . '/chat';

    $data = json_encode(['message' => $message]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    } else {
        return ['reply' => 'Sorry, chatbot service is temporarily unavailable.'];
    }
}

// Usage example:
// $result = callRenderChatbot("What are your services?");
// echo $result['reply'];
```

---

## ⚙️ Environment Variables

### For Render (Set in Render Dashboard)

| Variable | Description | Example |
|----------|-------------|---------|
| `PYTHON_VERSION` | Python version | `3.11.0` |
| `FLASK_ENV` | Flask environment | `production` |
| `CORS_ORIGINS` | Allowed origins for CORS | `https://blueviolet-seahorse-808517.hostingersite.com` |

### For Hostinger (Set in config.php)

| Variable | Description |
|----------|-------------|
| `DB_HOST` | Database host |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `DB_NAME` | Database name |
| `CHATBOT_API_URL` | Your Render service URL |
| `DEBUG_MODE` | Set to `false` in production |

---

## 🔒 Security Checklist

- [ ] Set `DEBUG_MODE` to `false` in production
- [ ] Verify `.htaccess` is protecting sensitive files
- [ ] Ensure database credentials are secure
- [ ] Test CORS settings are working correctly
- [ ] Verify HTTPS is enabled on both platforms
- [ ] Check file permissions (uploads/, logs/)
- [ ] Test error handling and logging
- [ ] Remove any `.env` files from public directories

---

## 📊 Monitoring & Maintenance

### Render Monitoring

1. **Check Logs:**
   - Go to Render Dashboard → Your Service → Logs
   - Monitor for errors and performance issues

2. **Health Checks:**
   - Render automatically pings `/health` endpoint
   - Set up notifications for downtime

### Hostinger Monitoring

1. **Error Logs:**
   - Check `logs/php-errors.log`
   - Monitor `logs/activity.log`

2. **Database Performance:**
   - Use phpMyAdmin to monitor queries
   - Check for slow queries

---

## 🐛 Troubleshooting

### Common Issues

#### 1. **Chatbot not responding**

**Problem:** Frontend can't connect to Render API

**Solutions:**
- Verify `CHATBOT_API_URL` is set correctly in `config.php`
- Check CORS settings in Render
- Test API endpoint directly: `curl https://your-render-url/health`
- Check Render logs for errors

#### 2. **500 Internal Server Error on Hostinger**

**Solutions:**
- Check `logs/php-errors.log`
- Verify database connection
- Ensure `.htaccess` is properly formatted
- Check file permissions

#### 3. **Database connection failed**

**Solutions:**
- Verify credentials in `config.php`
- Check database exists in Hostinger
- Import `autorepair_db.sql` if needed
- Test connection in phpMyAdmin

#### 4. **CORS errors in browser console**

**Solutions:**
- Update `CORS_ORIGINS` in Render environment variables
- Include your exact domain (with https://)
- Check browser console for specific error message

#### 5. **Render service sleeping (Free tier)**

**Problem:** First request takes 30+ seconds

**Solutions:**
- Upgrade to paid plan for always-on service
- Implement keep-alive ping from Hostinger
- Use Render cron jobs to keep service warm

---

## 🚀 Going Live Checklist

- [ ] Deploy Flask API to Render successfully
- [ ] Get Render service URL
- [ ] Update `CHATBOT_API_URL` in PHP config
- [ ] Upload all PHP files to Hostinger
- [ ] Import database to Hostinger MySQL
- [ ] Test website functionality
- [ ] Test chatbot integration
- [ ] Verify HTTPS on both platforms
- [ ] Set `DEBUG_MODE = false`
- [ ] Test all forms (login, signup, reservations)
- [ ] Check email functionality
- [ ] Monitor logs for 24 hours
- [ ] Set up backups

---

## 📞 Support Resources

- **Render Documentation:** https://render.com/docs
- **Hostinger Help:** https://www.hostinger.com/tutorials
- **Flask Documentation:** https://flask.palletsprojects.com/

---

## 📝 Quick Reference

### Render URLs
- Dashboard: https://dashboard.render.com/
- Your API: `https://papsi-chatbot-api.onrender.com` (example)

### Hostinger URLs
- Control Panel: https://hpanel.hostinger.com/
- Your Website: https://blueviolet-seahorse-808517.hostingersite.com
- phpMyAdmin: Access via Hostinger control panel

### Git Commands
```bash
# Commit changes
git add .
git commit -m "Deployment updates"
git push origin main

# Deploy to specific branch
git push origin claude/setup-deployment-architecture-016pjySxP6SLxt1ExJN29C6m
```

---

**Last Updated:** November 24, 2025
**Version:** 1.0.0
