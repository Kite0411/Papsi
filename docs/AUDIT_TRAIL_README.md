# Audit Trail System for Admin Panel

## Overview
The audit trail system provides comprehensive logging and tracking of all administrative actions in the Auto Repair Shop management system. This ensures accountability, security, and compliance by recording who did what, when, and from where.

## Features

### 🔍 **Comprehensive Logging**
- **All Admin Actions**: Every administrative action is automatically logged
- **Before/After Values**: Tracks changes to data with old and new values
- **User Context**: Records which admin performed each action
- **IP Tracking**: Logs the IP address of each action
- **Timestamp**: Precise timing of all actions

### 📊 **Action Types Tracked**
- **INSERT**: New records created (services, reservations, etc.)
- **UPDATE**: Existing records modified (status changes, edits, etc.)
- **DELETE**: Records removed from the system
- **APPROVE/DECLINE**: Reservation status changes

### 🎯 **Tables Monitored**
- `services` - Service management actions
- `reservations` - Reservation approval/decline actions
- `customers` - Customer data changes
- `users` - Admin user management
- `chat_knowledge` - Chatbot knowledge base changes

## Installation

### 1. Database Setup
Run the setup script to create the audit trail table:
```bash
php setup_audit_trail.php
```

Or manually execute the SQL:
```sql
-- See database/audit_trail_schema.sql
```

### 2. Configuration
The audit trail functions are automatically available in `config.php`:
- `logAuditTrail()` - Log new audit entries
- `getAuditTrail()` - Retrieve audit logs with filtering
- `getAuditTrailStats()` - Get audit statistics

## Usage

### Viewing Audit Logs
1. Navigate to **Admin Panel** → **Audit Trail**
2. Use filters to narrow down results:
   - **Action Type**: Filter by INSERT, UPDATE, DELETE
   - **Admin**: Filter by specific admin user
   - **Date Range**: Built-in pagination for time-based filtering

### Audit Log Details
Each audit entry includes:
- **Timestamp**: When the action occurred
- **Admin**: Who performed the action
- **Action**: Type of action (INSERT/UPDATE/DELETE)
- **Table**: Which database table was affected
- **Record ID**: Specific record that was modified
- **Description**: Human-readable description
- **IP Address**: Where the action originated
- **Details**: Expandable view of old/new values

## Dashboard Integration

### Statistics Cards
The admin dashboard now includes:
- **Total Audit Actions**: Count of all logged actions
- **Recent Actions (24h)**: Actions in the last 24 hours
- **Action Types**: Number of different action types
- **Active Admins**: Number of admins who have performed actions

### Navigation
- Added "Audit Trail" link to the main admin navigation
- Accessible from any admin page

## Security Features

### Data Protection
- **IP Logging**: Tracks the source of each action
- **User Agent**: Records browser/client information
- **Session Tracking**: Links actions to admin sessions
- **Immutable Logs**: Audit entries cannot be modified once created

### Compliance
- **Data Retention**: Audit logs are permanently stored
- **Change Tracking**: Complete before/after value tracking
- **User Accountability**: Every action is attributed to a specific admin

## Technical Implementation

### Database Schema
```sql
CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(100) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`)
);
```

### Function Usage
```php
// Log a new audit entry
logAuditTrail(
    'UPDATE',                    // Action type
    'reservations',             // Table name
    123,                        // Record ID
    ['status' => 'pending'],    // Old values
    ['status' => 'approved'],   // New values
    'Approved reservation'      // Description
);

// Get audit logs with filtering
$logs = getAuditTrail(50, 0, 'UPDATE', $adminId);

// Get statistics
$stats = getAuditTrailStats();
```

## Monitoring and Alerts

### Key Metrics to Monitor
- **High Activity**: Unusual number of actions by a single admin
- **Failed Actions**: Actions that might indicate security issues
- **Off-Hours Activity**: Actions outside normal business hours
- **Bulk Operations**: Large numbers of similar actions

### Recommended Alerts
- More than 100 actions in 1 hour by a single admin
- DELETE actions on critical tables
- Actions from unusual IP addresses
- Multiple failed login attempts

## Maintenance

### Regular Tasks
1. **Review Logs**: Weekly review of audit logs for anomalies
2. **Clean Old Data**: Consider archiving very old logs if needed
3. **Monitor Performance**: Ensure audit logging doesn't impact system performance
4. **Backup Logs**: Include audit logs in regular database backups

### Performance Considerations
- Indexes are optimized for common queries
- Pagination prevents large result sets
- JSON storage for old/new values is efficient
- Consider partitioning for very large datasets

## Troubleshooting

### Common Issues
1. **Missing Logs**: Check if `session_start()` is called before logging
2. **Permission Errors**: Ensure database user has INSERT permissions
3. **Performance Issues**: Check if indexes are properly created
4. **Empty Values**: Verify that old/new values are being captured correctly

### Debug Mode
Enable debug mode in `config.php` to see detailed error messages:
```php
define('DEBUG_MODE', true);
```

## Future Enhancements

### Planned Features
- **Export Functionality**: Export audit logs to CSV/PDF
- **Real-time Notifications**: Alerts for suspicious activities
- **Advanced Filtering**: Date range, specific field changes
- **Audit Reports**: Automated compliance reports
- **Data Visualization**: Charts and graphs for audit data

### Integration Opportunities
- **SIEM Systems**: Integration with security information systems
- **Compliance Tools**: Automated compliance checking
- **Backup Systems**: Integration with backup and recovery systems

## Support

For technical support or questions about the audit trail system:
1. Check this documentation first
2. Review the code comments in `config.php`
3. Test with the setup script: `setup_audit_trail.php`
4. Check database connectivity and permissions

---

**Note**: This audit trail system is designed for compliance and security. All administrative actions are logged and cannot be modified once recorded. Regular monitoring and review of audit logs is recommended for maintaining system security and compliance.

