# Romar System Documentation
**Last Updated:** April 1, 2026

## Table of Contents
1. [Installation & Setup](#installation--setup)
2. [Configuration](#configuration)
3. [API Documentation](#api-documentation)
4. [Database Schema](#database-schema)
5. [Security Best Practices](#security-best-practices)
6. [Performance Optimization](#performance-optimization)
7. [Troubleshooting](#troubleshooting)
8. [Deployment](#deployment)

---

## Installation & Setup

### Prerequisites
- PHP 7.2 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite
- Minimum 512MB RAM
- Linux or Windows server

### Quick Start

1. **Extract files to web root:**
```bash
tar -xzf romar.tar.gz -C /var/www/html/
cd /var/www/html/romar/
```

2. **Configure environment:**
```bash
cp .env.example .env
# Edit .env with your database credentials
```

3. **Setup database:**
```bash
php database/migrate.php
```

4. **Set permissions:**
```bash
chmod 755 -R /var/www/html/romar/
chmod 777 uploads/ logs/ database/backups/
```

5. **Access application:**
```
http://localhost/romar
```

### Default Credentials
- **Username:** admin
- **Password:** admin123
- **URL:** http://localhost/romar/auth/login.php

⚠️ **Change default password immediately after first login!**

---

## Configuration

### Environment Variables (.env)

```env
# Database
ROMAR_DB_HOST=localhost
ROMAR_DB_USER=root
ROMAR_DB_PASS=password
ROMAR_DB_NAME=romar_dormitory

# Application
ROMAR_APP_ENV=production  # dev, staging, production
ROMAR_APP_DEBUG=0         # 0=off, 1=on

# Backups
ROMAR_DB_BACKUP_RETENTION_DAYS=14
ROMAR_DB_BACKUP_MAX_ACTIVE=3

# Performance
ROMAR_CACHE_TTL=3600
ROMAR_MAX_RESULTS_PER_PAGE=50

# API
ROMAR_API_RATE_LIMIT=60
ROMAR_CORS_ORIGINS=*

# Email (optional)
ROMAR_MAIL_HOST=smtp.example.com
ROMAR_MAIL_PORT=587
```

### Configuration Override
Edit `config/config.php` for app-level settings without modifying `.env`

---

## API Documentation

### Authentication
All API endpoints require session authentication or CSRF token.

### Response Format
```json
{
  "success": true,
  "message": "Success",
  "data": { },
  "timestamp": "2026-04-01T10:30:00Z"
}
```

### Endpoints

#### Users
- `GET /api/getusers.php` - List users
- `POST /admin/userdocuments.php` - Create user

#### Tickets
- `GET /modules/ticket_view.php?id=123` - View ticket
- `POST /modules/tickets.php` - Create ticket
- `PUT` - Update ticket

#### Notifications
- `GET /api/getnotifications.php` - Get notifications
- `POST /api/marknotificationread.php` - Mark as read

### Rate Limiting
- Rate limit: 60 requests/minute per IP
- Headers returned:
  - `X-RateLimit-Limit: 60`
  - `X-RateLimit-Remaining: 55`
  - `X-RateLimit-Reset: 1234567890`

---

## Database Schema

### Core Tables

#### users
```sql
user_id (INT, PK)
username (VARCHAR 50, UNIQUE)
password_hash (VARCHAR 255)
email (VARCHAR 100)
full_name (VARCHAR 100)
role (ENUM: admin, staff, it_support, user)
is_active (BOOLEAN)
created_at (TIMESTAMP)
```

#### tickets
```sql
ticket_id (INT, PK)
ticket_number (VARCHAR 20, UNIQUE)
title (VARCHAR 255)
description (TEXT)
status (ENUM: new, assigned, in_progress, solved, closed)
priority (ENUM: low, medium, high, urgent)
assignee_id (INT, FK: users)
created_by (INT, FK: users)
created_at (TIMESTAMP)
```

#### assets
```sql
asset_id (INT, PK)
asset_name (VARCHAR 255)
asset_category (VARCHAR 100)
assigned_to (INT, FK: users)
status (ENUM: available, in_use, maintenance, retired)
purchase_date (DATE)
created_at (TIMESTAMP)
```

#### activity_logs
```sql
log_id (INT, PK)
user_id (INT, FK: users)
action (VARCHAR 50)
module (VARCHAR 50)
description (TEXT)
ip_address (VARCHAR 45)
created_at (TIMESTAMP)
```

---

## Security Best Practices

### Authentication & Authorization
✅ Use `isLoggedIn()` to check authentication  
✅ Use `isAdmin()` for admin checks  
✅ Check `$_SESSION['role']` for authorization  

```php
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (!isAdmin()) {
    die('Access denied');
}
```

### Input Validation
Always validate and sanitize user input:

```php
require_once 'includes/validation.php';

$validation = validate_batch([
    'email' => ['value' => $_POST['email'], 'type' => 'email'],
    'phone' => ['value' => $_POST['phone'] ?? '', 'type' => 'phone', 'country' => 'TH'],
    'age' => ['value' => $_POST['age'] ?? '', 'type' => 'integer', 'min' => 0, 'max' => 120]
]);

if (!$validation['valid']) {
    foreach ($validation['errors'] as $field => $error) {
        log_security_event('INVALID_INPUT', "Invalid $field: $error");
    }
    die('Invalid request');
}
```

### CSRF Protection
All POST forms must include CSRF token:

```php
// In template
<?php echo csrf_input(); ?>

// In handler
if (!verify_csrf()) {
    die('CSRF verification failed');
}
```

### SQL Injection Prevention
Always use prepared statements:

```php
// ✅ SAFE
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// ❌ UNSAFE - Don't do this!
$result = $db->query("SELECT * FROM users WHERE email = '$email'");
```

### Logging Security Events
Log all security-critical events:

```php
// Failed login
log_failed_login($username);

// Unauthorized access
log_security_event('ACCESS_DENIED', 'User tried to access admin panel', 'WARN');

// Data modifications
log_action('DELETE', 'users', $userId, getCurrentUserId(), ['ip' => get_remote_addr()]);
```

---

## Performance Optimization

### Database
1. **Add indexes for frequently queried columns:**
```sql
ALTER TABLE tickets ADD INDEX(user_id);
ALTER TABLE tickets ADD INDEX(status);
ALTER TABLE assets ADD INDEX(assigned_to);
```

2. **Use LIMIT in queries:**
```php
// Always add limit to prevent loading large datasets
$query = "SELECT * FROM logs ORDER BY created_at DESC LIMIT 1000";
```

### Pagination
Use built-in pagination for large data sets:

```php
$result = $db->query("SELECT COUNT(*) as total FROM tickets");
$row = $result->fetch_assoc();
$pagination = paginate($row['total'], $_GET['page'] ?? 1, 20);

$stmt = $db->prepare("SELECT * FROM tickets LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pagination['per_page'], $pagination['offset']);
$stmt->execute();
```

### Caching
Cache frequently accessed data:

```php
// Get from cache or database
$announcements = get_cache('announcements');
if (!$announcements) {
    $result = $db->query("SELECT * FROM announcements WHERE is_active = 1");
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
    set_cache('announcements', $announcements, 3600); // 1 hour TTL
}
```

### Monitoring Performance
Enable performance logging:

```php
$start = microtime(true);
// ... code ...
$elapsed = microtime(true) - $start;
log_performance($_SERVER['REQUEST_URI'], $elapsed, $db->num_queries, memory_get_usage());
```

---

## Troubleshooting

### 500 Internal Server Error
1. Check `/logs/errors_*.json` for detailed errors
2. Enable debug mode in `.env`: `ROMAR_APP_DEBUG=1`
3. Check Apache error log: `/var/log/apache2/error.log`

### Database Connection Failed
```php
// Check credentials in .env
ROMAR_DB_HOST=localhost
ROMAR_DB_USER=root
ROMAR_DB_PASS=password
ROMAR_DB_NAME=romar_dormitory

// Test connection
mysql -h localhost -u root -p romar_dormitory -e "SELECT 1;"
```

### Session Issues
1. Check if `uploads/` directory has write permissions
2. Verify `session.save_path` in php.ini
3. Clear browser cookies and login again

### Slow Performance
1. Check `/logs/performance_*.json` for bottlenecks
2. Run `php database/backup_manager.php cleanup`
3. Check database indexes
4. Monitor memory usage and enable caching

---

## Deployment

### Development to Production Checklist

- [ ] Backup current database
- [ ] Set `ROMAR_APP_DEBUG=0`
- [ ] Set `ROMAR_APP_ENV=production`
- [ ] Update `.env` credentials
- [ ] Set file permissions `chmod 755 -R`
- [ ] Run database migrations
- [ ] Test all features
- [ ] Monitor logs for errors
- [ ] Setup automated backups
- [ ] Schedule maintenance tasks

### Automated Tasks (Cron)

Create cron jobs for maintenance:

```bash
# Daily backup
0 2 * * * php /var/www/html/romar/database/backup_manager.php create

# Weekly cleanup
0 3 * * 0 php /var/www/html/romar/database/backup_manager.php cleanup

# Cleanup old logs (monthly)
0 4 1 * * find /var/www/html/romar/logs -mtime +30 -delete
```

### Health Monitoring
Check system health:

```bash
curl http://localhost/romar/health/db.php
```

---

## Key Resources

- **API Security:** includes/api_security.php
- **Validation:** includes/validation.php  
- **Logging:** includes/logger.php
- **Safe Access:** includes/safe_access.php
- **Backup Management:** database/backup_manager.php

## Support & Maintenance

For issues or updates:
1. Check documentation
2. Review logs in `/logs/`
3. Contact development team
4. Submit bug report with logs

---

*Documentation Version: 1.0*  
*Last Updated: April 1, 2026*
