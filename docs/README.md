# AutoFix Repair Shop - AI Assistant System

## Overview

AutoFix is a comprehensive auto repair shop management system with an intelligent AI assistant that helps customers with inquiries, service bookings, and general automotive questions.

## 🚀 New Features in Version 2.0

### 🤖 Enhanced AI Assistant
- **Modern UI**: Beautiful, responsive chatbot interface with animations
- **Dual AI System**: Combines database knowledge base with Hugging Face AI API
- **Smart Responses**: Context-aware auto repair responses
- **Typing Indicators**: Real-time typing animations
- **Minimize/Maximize**: Collapsible chat interface
- **Mobile Responsive**: Works perfectly on all devices

### 🎨 Improved User Experience
- **Professional Design**: Modern gradient colors and smooth animations
- **Better Navigation**: Enhanced admin dashboard with chatbot statistics
- **Real-time Feedback**: Instant response with loading states
- **Error Handling**: Graceful error handling with fallback responses

### 🔧 Admin Management
- **Chatbot Dashboard**: Comprehensive admin interface for managing AI knowledge
- **Statistics Tracking**: Monitor conversations and knowledge base usage
- **Knowledge Management**: Add, edit, and delete chatbot responses
- **Chat History**: View and analyze customer interactions
- **Training Interface**: Easy-to-use bot training system

## 📁 File Structure

```
Papsi/
├── admin/
│   ├── index.php                 # Enhanced admin dashboard
│   ├── manage_chatbot.php        # Chatbot management interface
│   └── ...                       # Other admin files
├── chatbot-ui.php                # Modern chatbot UI component
├── chat.php                      # Enhanced AI backend
├── config.php                    # Centralized configuration
├── train.php                     # Improved training interface
├── index.php                     # Main page with new chatbot
└── ...                           # Other application files
```

## 🛠️ Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)
- cURL extension enabled

### Database Setup
1. Import the `database/autorepair_db.sql` file
2. Update database credentials in `config.php`
3. Ensure the `chat_knowledge` and `chat_history` tables exist

### Configuration
1. Edit `config.php` with your settings:
   ```php
   // Database settings
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'your_username');
   define('DB_PASSWORD', 'your_password');
   
   // AI settings
   define('HUGGING_FACE_TOKEN', 'your_token_here');
   ```

2. Set up your Hugging Face API token:
   - Visit [Hugging Face](https://huggingface.co/)
   - Create an account and get your API token
   - Update the token in `config.php`

## 🎯 How to Use

### For Customers
1. Visit the main page (`index.php`)
2. The chatbot appears as a floating widget in the bottom-right corner
3. Click to expand and start chatting
4. Ask about services, pricing, or book appointments

### For Administrators
1. Access the admin panel (`admin/index.php`)
2. Navigate to "Chatbot" in the menu
3. Manage knowledge base and view statistics
4. Use the training interface to improve responses

### Training the Bot
1. Go to `train.php` or use the admin interface
2. Add trigger phrases and corresponding responses
3. The system learns from interactions automatically
4. Monitor performance through admin dashboard

## 🔧 Key Features

### AI Assistant Capabilities
- **Service Information**: Provides details about available services
- **Pricing Queries**: Answers questions about service costs
- **Booking Assistance**: Helps with appointment scheduling
- **General Support**: Handles common automotive questions
- **Fallback Responses**: Graceful handling of unknown queries

### Smart Learning
- **Database Knowledge**: Stores and retrieves learned responses
- **Similarity Matching**: Uses Levenshtein distance for fuzzy matching
- **AI Enhancement**: Falls back to Hugging Face API for complex queries
- **Context Awareness**: Auto repair specific responses

### Security Features
- **Input Sanitization**: All user inputs are properly sanitized
- **SQL Injection Protection**: Prepared statements for database queries
- **CSRF Protection**: Built-in CSRF token validation
- **Activity Logging**: Comprehensive audit trail

## 🎨 UI/UX Improvements

### Modern Design
- **Gradient Headers**: Professional blue gradient design
- **Smooth Animations**: CSS transitions and keyframe animations
- **Responsive Layout**: Works on desktop, tablet, and mobile
- **Professional Typography**: Clean, readable fonts

### User Experience
- **Minimize/Maximize**: Collapsible chat interface
- **Typing Indicators**: Shows when bot is "thinking"
- **Message Avatars**: Visual distinction between user and bot
- **Auto-scroll**: Automatically scrolls to new messages
- **Keyboard Support**: Enter key to send messages

## 📊 Admin Features

### Dashboard Statistics
- Total knowledge entries
- Conversation count
- Today's interactions
- Visual charts and metrics

### Knowledge Management
- Add new responses
- Edit existing knowledge
- Delete outdated entries
- Bulk operations

### Chat History
- View all conversations
- Search and filter
- Export capabilities
- Analytics and insights

## 🔒 Security Considerations

### Data Protection
- All user inputs are sanitized
- Database queries use prepared statements
- Session management with timeout
- CSRF token validation

### API Security
- API tokens stored in configuration
- Rate limiting for API calls
- Error logging without exposing sensitive data
- Secure fallback mechanisms

## 🚀 Performance Optimizations

### Database
- Indexed queries for fast retrieval
- Connection pooling
- Prepared statements for efficiency

### Frontend
- Optimized CSS and JavaScript
- Lazy loading for better performance
- Minimal DOM manipulation
- Efficient event handling

## 🐛 Troubleshooting

### Common Issues

1. **Chatbot not responding**
   - Check API token in `config.php`
   - Verify database connection
   - Check browser console for errors

2. **Database errors**
   - Ensure tables exist
   - Check database credentials
   - Verify PHP mysqli extension

3. **Styling issues**
   - Clear browser cache
   - Check CSS file paths
   - Verify Bootstrap CDN links

### Debug Mode
Enable debug mode in `config.php`:
```php
define('DEBUG_MODE', true);
```

## 📈 Future Enhancements

### Planned Features
- **Multi-language Support**: Internationalization
- **Voice Integration**: Speech-to-text capabilities
- **Advanced Analytics**: Detailed conversation insights
- **Integration APIs**: Connect with external services
- **Machine Learning**: Improved response accuracy

### Customization
- **Theme System**: Customizable colors and styles
- **Plugin Architecture**: Extensible functionality
- **API Endpoints**: RESTful API for external integration
- **Webhook Support**: Real-time notifications

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the troubleshooting section
- Review the configuration options
- Contact the development team
- Submit issues on GitHub

---

**AutoFix Repair Shop AI Assistant** - Making auto repair services smarter and more accessible! 🚗🔧🤖 