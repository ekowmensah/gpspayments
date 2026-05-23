# GPS Payments - Development Setup Guide

## Prerequisites

- **PHP 7.4+** (recommend 8.1+)
- **MySQL 5.7+** (recommend 8.0+)
- **Git** for version control
- **Composer** (optional, for dependency management)
- **Code Editor**: VS Code or PHPStorm

---

## Step 1: Environment Setup

### 1.1 Create `.env` File
```bash
cd c:\xampp\htdocs\gpspayments
copy .env.example .env
```

### 1.2 Configure `.env`
```env
# Application Settings
APP_ENV=development
APP_URL=http://localhost/gpspayments
APP_NAME=GPS Payments
APP_TIMEZONE=Africa/Accra

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASS=          # Leave empty if no password
DB_NAME=gpspayments
DB_PORT=3306

# Payment Gateway Keys (optional for now)
MOMO_API_KEY=
MOMO_API_SECRET=
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
```

### 1.3 Create Database
```sql
-- In phpMyAdmin or MySQL CLI:
CREATE DATABASE IF NOT EXISTS gpspayments 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### 1.4 Import Schema
```bash
# In MySQL CLI:
mysql -u root gpspayments < database/schema.sql

# Or in phpMyAdmin:
# 1. Select gpspayments database
# 2. Import database/schema.sql
```

---

## Step 2: Verify Database Connection

Create a quick test file `public/test-connection.php`:

```php
<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

try {
    $conn = db();
    
    // Test connection
    $result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
    $row = $result->fetch_assoc();
    
    echo "✅ Database Connected Successfully!\n";
    echo "Tables Found: " . $row['table_count'] . "\n";
    
    // List all tables
    $tables = $conn->query("SHOW TABLES");
    echo "\nDatabase Tables:\n";
    while ($row = $tables->fetch_row()) {
        echo "  - " . $row[0] . "\n";
    }
    
    close_db();
} catch (Exception $e) {
    echo "❌ Connection Failed: " . $e->getMessage();
}
?>
```

Access: `http://localhost/gpspayments/test-connection.php`

---

## Step 3: Project Structure

### Create Missing Directories
```bash
mkdir src\services
mkdir src\models
mkdir src\controllers
mkdir src\utils
mkdir public\uploads
mkdir public\uploads\photos
mkdir public\uploads\receipts
mkdir logs
mkdir tests\unit
mkdir tests\integration
```

### Directory Permissions
```bash
# Give write permissions to logs and uploads
chmod 755 logs
chmod 755 public/uploads
```

---

## Step 4: Initial Project Files

### 4.1 Create `public/index.php` (Entry Point)
```php
<?php
/**
 * GPS Payments - Application Entry Point
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? 1 : 0);

// Load configuration
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Session start
session_start();

// Simple routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH);
$route = substr($request_uri, strlen($script_name));

// Remove leading slash
$route = ltrim($route, '/');

// Temporary: Show available routes
if (empty($route) || $route === 'index.php') {
    echo "✅ GPS Payments System is running!\n";
    echo "Available endpoints (to be implemented):\n";
    echo "- POST /auth/login\n";
    echo "- POST /auth/logout\n";
    echo "- GET /dashboard\n";
    echo "- POST /payments/record\n";
    echo "- GET /members\n";
    exit;
}

// TODO: Implement routing system
echo "Route: $route (not implemented yet)";
?>
```

### 4.2 Create `config/settings.php`
```php
<?php
/**
 * Application Settings
 */

// Feature Flags
define('FEATURES', [
    'sms_notifications' => true,
    'email_notifications' => true,
    'qr_codes' => true,
    'bulk_import' => false,
    'mobile_app_api' => false,
    'advanced_reporting' => true,
]);

// Email Configuration
define('MAIL_FROM', $_ENV['MAIL_FROM'] ?? 'noreply@gpspayments.com');
define('MAIL_DRIVER', $_ENV['MAIL_DRIVER'] ?? 'smtp');
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT', $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USER', $_ENV['MAIL_USER'] ?? '');
define('MAIL_PASS', $_ENV['MAIL_PASS'] ?? '');

// SMS Configuration
define('SMS_PROVIDER', $_ENV['SMS_PROVIDER'] ?? 'twilio');
define('SMS_FROM', $_ENV['SMS_FROM'] ?? 'GHSPayments');

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Payment Configuration
define('RECEIPT_PREFIX', 'GPS');
define('MIN_PAYMENT_AMOUNT', 0.01);
define('MAX_PAYMENT_AMOUNT', 100000);

// Session Configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_NAME', 'GPSPAYMENTS_SESSION');
?>
```

### 4.3 Create Basic Logger (`src/utils/Logger.php`)
```php
<?php
namespace App\Utils;

class Logger {
    private $logDir = __DIR__ . '/../../logs/';
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function debug($message, $context = []) {
        if (APP_ENV === 'development') {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message";
        
        if (!empty($context)) {
            $log_message .= "\nContext: " . json_encode($context);
        }
        
        $log_file = $this->logDir . 'application.log';
        error_log($log_message . "\n", 3, $log_file);
    }
}
?>
```

### 4.4 Create `.gitignore`
```
# Environment variables
.env
.env.local
.env.*.local

# IDE
.vscode/
.idea/
*.swp
*.swo
*.sublime-project

# Dependencies
vendor/
node_modules/

# Logs and uploads
logs/
public/uploads/

# OS
.DS_Store
Thumbs.db

# Temporary files
*.tmp
*.bak
*.swp

# Database backups
*.sql.backup
*.bak

# Cache
__pycache__/
*.pyc
```

---

## Step 5: Testing the Setup

### 5.1 Check File Structure
```bash
cd c:\xampp\htdocs\gpspayments
tree /F /L 3
```

Expected output should show all directories are created.

### 5.2 Verify Database Tables
Open `http://localhost/phpmyadmin/`:
1. Select `gpspayments` database
2. Should see tables: associations, members, payments, etc.

### 5.3 Test Application
Open `http://localhost/gpspayments/public/`

Should see: `✅ GPS Payments System is running!`

---

## Step 6: Next Development Steps

### Create Your First Feature: Authentication

1. **Create Auth Model** (`src/models/User.php`)
2. **Create Auth Service** (`src/services/AuthService.php`)
3. **Create Auth Controller** (`src/controllers/AuthController.php`)
4. **Create Login View** (`views/auth/login.php`)
5. **Add Routing** in `public/index.php`

### Recommended Order:
1. User authentication (login/logout)
2. Dashboard middleware
3. Member management (CRUD)
4. Payment recording
5. Payment verification
6. Reporting

---

## Step 7: Development Commands

### Useful MySQL Commands
```bash
# Connect to database
mysql -u root gpspayments

# Backup database
mysqldump -u root gpspayments > backup.sql

# Restore database
mysql -u root gpspayments < backup.sql

# Clear all data (keep structure)
mysql -u root -e "USE gpspayments; TRUNCATE payments; TRUNCATE members; TRUNCATE users;"
```

### Git Commands
```bash
# Initialize repository
git init
git add .
git commit -m "Initial project setup"

# Create feature branch
git checkout -b feature/authentication

# Commit changes
git add .
git commit -m "Implement login system"
```

---

## Step 8: Development Workflow

### Daily Workflow
```
1. Pull latest code
   git pull origin main

2. Create feature branch
   git checkout -b feature/your-feature

3. Implement feature
   - Write code
   - Test manually
   - Check error logs

4. Commit changes
   git add .
   git commit -m "Descriptive message"

5. Push to repository
   git push origin feature/your-feature

6. Create Pull Request for review
```

### Testing Changes
- Always test in development mode (APP_ENV=development)
- Check browser console for JS errors
- Check `logs/application.log` for server errors
- Check `logs/errors.log` for exceptions

---

## Troubleshooting

### Database Connection Error
```
Error: Connection failed: ...
```
**Solution**:
- Check DB credentials in `.env`
- Ensure MySQL service is running
- Verify database exists: `SHOW DATABASES;`

### Permission Denied on Logs
```
Permission denied: logs/application.log
```
**Solution**:
```bash
chmod 755 logs/
chmod 755 public/uploads/
```

### Module Not Found Error
```
Fatal error: Uncaught Error: Class 'App\Models\User' not found
```
**Solution**:
- Check PHP namespace matches file path
- Ensure file names match class names (case-sensitive on Linux)
- Use correct require_once paths

---

## Resources

- **PHP Documentation**: https://www.php.net/manual/
- **MySQL Documentation**: https://dev.mysql.com/doc/
- **Payment Integrations**:
  - MTN Mobile Money: https://developer.mtn.com/
  - Vodafone Cash: https://vodafone.com.gh/
  - Twilio SMS: https://www.twilio.com/

---

**Ready to start development!** Begin with Step 1 and follow through. Good luck! 🚀

---

## Seed Default Data (New)

After schema import, run:

```bash
php scripts/seed.php
php scripts/seed.php --with-demo
```

Default admin credentials:
- Email: `admin@gpspayments.local`
- Password: `Admin123!`

Operational pages:
- `http://localhost/gpspayments/public/members/page`
- `http://localhost/gpspayments/public/payments/page`
- `http://localhost/gpspayments/public/reconciliation/page`
