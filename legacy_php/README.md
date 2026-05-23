# GPS Payments - Member Association Collection Management System

A comprehensive web-based system for managing member registrations, dues collection, payment processing, and financial reporting for membership associations and organizations.

**Version:** 1.0.0  
**Status:** Development Phase  
**Stack:** PHP 7.4+, MySQL 5.7+, HTML5, CSS3, JavaScript

---

## 📋 Table of Contents

- [Features](#features)
- [System Architecture](#system-architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Project Structure](#project-structure)
- [API Workflow](#api-workflow)
- [Payment Processing](#payment-processing)
- [User Roles & Permissions](#user-roles--permissions)
- [Workflow Documentation](#workflow-documentation)
- [Development](#development)

---

## 🚀 Features

### Core Functionality
- **Member Management** — Registration, profiles, status tracking
- **Groups/Branches** — Organizational sub-divisions (optional)
- **Collection Items** — Create recurring dues, levies, and voluntary donations
- **Payment Processing** — Multiple methods (Cash, Mobile Money, Bank, USSD, Card)
- **Verification & Reconciliation** — Prevent duplicates, fraud, and discrepancies
- **Receipts** — Auto-generated digital receipts with QR codes
- **Arrears Tracking** — Expected vs. paid, defaulters, partial payments
- **Financial Reports** — Daily, monthly, yearly with multiple export formats
- **SMS/Email Notifications** — Automated alerts and confirmations
- **Audit Logging** — Complete transaction history for compliance

### Security & Compliance
- Role-based access control (Admin, Treasurer, Secretary, Auditor, Member)
- Encrypted password storage
- Session management with timeout
- Comprehensive audit trail
- Input validation and XSS protection
- CSRF token validation

### Payment Workflow Improvements
- **Payment Method Routing** — Different workflows for cash vs. digital
- **Duplicate Detection** — Prevents same payment recorded twice
- **Verification Checkpoints** — Amount validation, fraud checks
- **Reconciliation** — Manual for cash, automatic for digital
- **Error Handling** — Retries, timeouts, manual overrides
- **Overpayment Management** — Credit, refund, or donation options

---

## 🏗️ System Architecture

### Directory Structure

```
gpspayments/
├── config/                    # Configuration files
│   ├── database.php          # Database connection & queries
│   ├── constants.php         # Application constants & enums
│   └── settings.php          # Application settings & features
├── database/
│   ├── schema.sql            # Database schema & tables
│   └── migrations/           # Future database migrations
├── public/                   # Web root (served by server)
│   ├── index.php            # Application entry point
│   ├── css/                 # Stylesheets
│   ├── js/                  # Client-side JavaScript
│   ├── assets/              # Images, icons, fonts
│   └── uploads/             # User uploads (member photos, etc.)
├── src/
│   ├── controllers/         # Business logic & request handling
│   ├── models/              # Database models & queries
│   ├── services/            # Payment, notification, report services
│   ├── middleware/          # Auth, logging, error handling
│   └── utils/               # Helper functions & utilities
├── views/                   # HTML templates (Blade-like syntax)
├── logs/                    # Application logs & audit trails
├── tests/                   # Unit & integration tests
├── IMPROVED_WORKFLOW.md    # Detailed payment workflow documentation
├── .env.example            # Environment configuration template
├── .env                    # Actual environment config (not in git)
└── README.md               # This file
```

### Database Schema

**Core Tables:**
- `associations` — Organization details
- `members` — Member records
- `branches` — Sub-groups/branches (optional)
- `collection_items` — Dues, levies, donations
- `member_collections` — Collection assignments

**Payment Tables:**
- `payments` — Payment records with status tracking
- `payment_verifications` — Verification results
- `receipts` — Generated receipts

**Reconciliation Tables:**
- `reconciliation_batches` — Batch reconciliation records
- `reconciliation_items` — Items in each batch

**Audit & Notifications:**
- `audit_logs` — Complete action history
- `notifications` — SMS/Email logs

**User Management:**
- `users` — User accounts
- `roles` — Role definitions

**Other:**
- `donations` — Donation tracking

**Views:**
- `member_arrears` — Calculated arrears status per member

---

## 💾 Installation

### Prerequisites

- **Web Server:** Apache 2.4+ with mod_rewrite
- **PHP:** 7.4+ (recommended 8.0+)
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Server:** XAMPP, WampServer, or similar AMP stack

### Step 1: Clone/Download Project

```bash
cd /c/xampp/htdocs/
git clone <repository-url> gpspayments
# or download ZIP and extract to gpspayments/
```

### Step 2: Install Dependencies

If using Composer (for future vendor packages):
```bash
cd gpspayments
composer install
```

### Step 3: Configure Environment

Copy the example environment file:
```bash
cp .env.example .env
```

Edit `.env` with your settings:
```ini
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=gpspayments
APP_ENV=development
APP_URL=http://localhost/gpspayments
```

### Step 4: Create Database

```bash
# Using MySQL command line
mysql -u root -p < database/schema.sql

# Or use phpmyadmin:
# 1. Create new database "gpspayments"
# 2. Import database/schema.sql
```

### Step 5: Set Permissions

```bash
# Make logs and uploads writable
chmod -R 755 logs/
chmod -R 755 public/uploads/
chmod 644 .env
```

### Step 6: Verify Installation

Visit: `http://localhost/gpspayments`

Expected: Login page loads successfully

---

## ⚙️ Configuration

### Environment Variables (.env)

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | development | Environment (development/production) |
| `DB_HOST` | localhost | Database host |
| `DB_USER` | root | Database user |
| `DB_PASS` | (empty) | Database password |
| `DB_NAME` | gpspayments | Database name |
| `SMS_API_KEY` | (empty) | SMS gateway API key |
| `MOMO_API_KEY` | (empty) | Mobile Money API key |
| `MOMO_CALLBACK_SECRET` | (empty) | MoMo webhook secret |

### Application Settings (config/settings.php)

Key configuration objects:

```php
$PAYMENT_GATEWAY_CONFIG     // Payment method settings
$NOTIFICATION_CONFIG        // SMS/Email settings
$LOG_CONFIG                 // Logging configuration
$VERIFICATION_RULES         // Payment verification rules
$RECONCILIATION_CONFIG      // Reconciliation settings
$FEATURE_FLAGS             // Feature toggles
```

### Feature Flags

Enable/disable features in `config/settings.php`:

```php
$FEATURE_FLAGS = [
    'enable_member_portal' => true,
    'enable_sms_reminders' => true,
    'require_payment_verification' => true,
    'require_payment_reconciliation' => true,
    // ... more flags
];
```

---

## 🗄️ Database Setup

### Create Database

```sql
CREATE DATABASE gpspayments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Run Schema

```bash
mysql -u root -p gpspayments < database/schema.sql
```

### Verify Tables

```bash
mysql -u root -p gpspayments -e "SHOW TABLES;"
```

Expected tables: 15+ tables including members, payments, audit_logs, etc.

### Insert Default Roles

Default roles are inserted by schema.sql:
- Administrator
- Treasurer
- Secretary
- Auditor
- Member

---

## 📁 Project Structure Details

### config/
- **database.php** — DB connection management
- **constants.php** — Enums, statuses, role permissions
- **settings.php** — Feature flags, gateway configs

### src/controllers/
- LoginController — Authentication
- DashboardController — Dashboard data
- PaymentController — Payment recording/verification
- ReconciliationController — Reconciliation batches
- MemberController — Member management
- ReportController — Report generation

### src/models/
- Payment — Payment queries
- Member — Member queries
- Collection — Collection queries
- AuditLog — Audit log queries

### src/services/
- PaymentService — Payment processing logic
- VerificationService — Duplicate detection, validation
- NotificationService — SMS/Email sending
- ReportService — Report generation
- ReconciliationService — Batch reconciliation

### src/middleware/
- AuthMiddleware — Session validation
- RoleMiddleware — Permission checking
- LoggingMiddleware — Request logging
- CSRFMiddleware — CSRF protection

### views/
- login.php
- dashboard.php
- member/list.php, form.php
- payment/record.php, verify.php
- reports/daily.php, monthly.php
- ...

---

## 💳 Payment Processing

### Payment Methods

1. **Cash**
   - Recorded by Treasurer
   - Requires end-of-day reconciliation
   - Status: PENDING_RECONCILIATION → CONFIRMED

2. **Mobile Money**
   - Webhook callback from provider
   - Auto-reconciliation with provider
   - Status: PENDING_VERIFICATION → CONFIRMED

3. **Bank Transfer**
   - Manual entry with proof (screenshot)
   - Manual reconciliation against bank statement
   - Status: PENDING_RECONCILIATION → CONFIRMED

4. **USSD**
   - Manual entry with confirmation code
   - Verification via provider API (if available)
   - Status: PENDING_VERIFICATION → CONFIRMED

### Payment Status Flow

```
Pending_Entry
    ↓
Pending_Verification (Duplicate check, amount validation, fraud check)
    ↓
Pending_Reconciliation (For cash; auto for digital)
    ↓
Verified/Confirmed ← Receipt generated, member notified
    ↓
[Final Status - no changes allowed]
```

### Verification Checks

All payments pass through:
1. **Duplicate Detection** — Same member + amount within 24 hours
2. **Amount Validation** — Positive, reasonable, within limits
3. **Status Checks** — Member active, collection active
4. **Fraud Detection** — Burst detection, outlier detection

See `IMPROVED_WORKFLOW.md` for complete details.

---

## 👥 User Roles & Permissions

### Roles

| Role | Access | Permissions |
|------|--------|-------------|
| **Administrator** | Full | Users, settings, all reports, backups |
| **Treasurer** | Financial | Record payments, reconcile, verify |
| **Secretary** | Records | Register members, view records |
| **Auditor** | Read-only | View reports, audit logs |
| **Member** | Limited | Own account, dues, receipts |

Permissions defined in `config/constants.php`:
```php
define('ROLE_PERMISSIONS', [
    'Administrator' => ['manage_users', 'record_payments', ...],
    'Treasurer' => ['record_payments', 'verify_payments', ...],
    // ...
]);
```

---

## 📖 Workflow Documentation

### Main Workflows

1. **Member Registration**
   - Admin/Secretary adds member
   - System assigns ID
   - Audit log created

2. **Collection Setup**
   - Admin creates collection item (dues, levy, etc.)
   - Configures amount, frequency, applicability
   - System assigns to members

3. **Payment Recording** (See IMPROVED_WORKFLOW.md)
   - Member pays via selected method
   - Different workflows by method
   - Verification → Reconciliation → Confirmation

4. **Reconciliation**
   - **Cash:** End-of-day treasurer batch reconciliation
   - **Digital:** Auto-reconciliation against provider

5. **Reporting**
   - Admin/Treasurer generates reports
   - Exports to PDF, Excel, CSV
   - Audit logs available for all actions

### Key Documents

- **IMPROVED_WORKFLOW.md** — Detailed payment workflow with improvements
- **database/schema.sql** — Database structure & relationships
- **config/constants.php** — All system enums and constants

---

## 🔧 Development

### Adding a New Payment Method

1. Add to PAYMENT_METHODS in `config/constants.php`
2. Create route handler in PaymentController
3. Create verification logic in VerificationService
4. Add reconciliation logic in ReconciliationService
5. Update IMPROVED_WORKFLOW.md

### Adding a New Report

1. Create report view in `views/reports/`
2. Add report method in ReportController
3. Create query in ReportService
4. Register in REPORT_TYPES constant

### Adding a New Notification Type

1. Add template in NOTIFICATION_TEMPLATES
2. Create notification trigger in appropriate service
3. Send via NotificationService
4. Log in notifications table

---

## 🔒 Security Considerations

- **Input Validation** — All user input validated server-side
- **SQL Injection** — Parameterized queries used
- **XSS Protection** — Output escaped, CSP headers set
- **CSRF** — Token validation on all forms
- **Authentication** — Secure session management
- **Passwords** — bcrypt hashing with salt
- **Audit Trail** — All actions logged with user/timestamp

---

## 📊 Monitoring & Logging

Logs stored in `logs/` directory:

- `error.log` — System errors
- `payment.log` — Payment transactions
- `audit.log` — User actions (also in DB)
- `notification.log` — SMS/Email delivery

---

## 🤝 Contributing

1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes following project structure
3. Test thoroughly
4. Commit with clear message
5. Push and create pull request

---

## 📝 License

Proprietary - Member Association Collection Management System

---

## 📞 Support

For issues or questions:
- Check IMPROVED_WORKFLOW.md for workflow questions
- Review config/constants.php for configuration
- Check database/schema.sql for data structure
- Enable development logging for debugging

---

**Last Updated:** 2026-05-21  
**Next Phase:** Implementation of controllers, services, and views

---

## Current Dev Bootstrap (Implemented)

### Seed default data
```bash
php scripts/seed.php
php scripts/seed.php --with-demo
```

Default admin login:
- `admin@gpspayments.local`
- `Admin123!`

### Operational pages
- `GET /members/page`
- `GET /payments/page`
- `GET /reconciliation/page`
- `GET /reports/page`
- `GET /audit/page`

### Test scaffold
```bash
phpunit
# or
vendor\bin\phpunit
```
