# Papsi Auto Repair Shop - Project Structure

## 📁 Organized Directory Structure

```
Papsi/
├── admin/                      # Admin panel files
│   ├── admin.php              # Admin dashboard
│   ├── audit_trail.php        # Audit trail viewer
│   ├── edit_service.php       # Service editor
│   ├── index.php              # Admin home
│   ├── manage_chatbot.php     # Chatbot management
│   ├── manage_reservations.php # Reservation management
│   ├── manage_services.php    # Service management
│   └── walk_in.php            # Walk-in customer handling
│
├── auth/                       # Authentication & user management
│   ├── login.php              # User login
│   ├── signup.php             # User registration
│   ├── logout.php             # Logout handler
│   ├── forgot_password.php    # Password recovery
│   ├── reset_password.php     # Password reset
│   ├── reset_code.php         # OTP verification
│   ├── verify_otp.php         # OTP validation
│   ├── new_password.php       # New password form
│   ├── password_changed.php   # Success page
│   └── auth_api.php           # Authentication API
│
├── chatbot/                    # AI Chatbot system
│   ├── chat.php               # Chat API endpoint
│   ├── chatbot-ui.php         # Chatbot UI component
│   └── train.php              # Chatbot training interface
│
├── database/                   # Database schemas
│   ├── autorepair_db.sql      # Main database schema
│   ├── audit_trail_schema.sql # Audit trail schema
│   └── payments_table.sql     # Payments table schema
│
├── docs/                       # Documentation
│   ├── README.md              # Project documentation
│   └── AUDIT_TRAIL_README.md  # Audit trail documentation
│
├── includes/                   # Shared configuration
│   └── config.php             # Database & app configuration
│
├── logs/                       # Application logs
│   └── (activity logs stored here)
│
├── reservations/               # Reservation & Payment system
│   ├── reservation.php        # Booking form
│   └── payment.php            # Payment page with GCash QR
│
├── scripts/                    # Utility scripts
│   ├── add_diagnostic_knowledge.php  # Chatbot knowledge seeder
│   ├── add_services_knowledge.sql    # Service data seeder
│   └── setup_payments.php     # Payment system setup script
│
├── uploads/                    # User uploaded files
│   ├── (service photos stored here)
│   └── payments/              # Payment proof screenshots
│
├── vendor/                     # Composer dependencies
│   └── (PHPMailer & dependencies)
│
├── index.php                   # Main landing page
├── composer.json               # PHP dependencies
└── composer.lock               # Dependency lock file
```

## 🔗 Important Path Updates

### Configuration File
All PHP files now include: `include '../includes/config.php';` (or appropriate relative path)

### Authentication URLs
- Login: `/auth/login.php`
- Signup: `/auth/signup.php`
- Logout: `/auth/logout.php`

### Main Features
- Home: `/index.php`
- Reservations: `/reservations/reservation.php`
- Payment: `/reservations/payment.php`
- Chatbot: Embedded via `/chatbot/chatbot-ui.php`
- Admin Panel: `/admin/index.php`

### API Endpoints
- Chat API: `/chatbot/chat.php`
- Auth API: `/auth/auth_api.php`

## 🚀 Getting Started

1. **Database Setup**
   - Import `/database/autorepair_db.sql`
   - Import `/database/audit_trail_schema.sql`
   - Run `/scripts/setup_payments.php` to create payments table

2. **Configuration**
   - Update `/includes/config.php` with your database credentials

3. **Dependencies**
   - Run `composer install` to install PHPMailer

4. **Access Points**
   - User Portal: `http://localhost/Papsi/`
   - Admin Panel: `http://localhost/Papsi/admin/`
   - Login: `http://localhost/Papsi/auth/login.php`

## 📝 Notes

- All authentication files are in `/auth/`
- All chatbot-related files are in `/chatbot/`
- Configuration is centralized in `/includes/config.php`
- Logs are stored in `/logs/`
- Uploaded files go to `/uploads/`
- Documentation is in `/docs/`

## 🔒 Security

- Session management in auth files
- Password hashing with PHP password_hash()
- SQL injection prevention with prepared statements
- Input sanitization via config.php functions
- Audit trail logging for admin actions
