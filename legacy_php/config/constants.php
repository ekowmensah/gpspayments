<?php
/**
 * Application Constants
 *
 * All system constants and enums defined here
 */

// ============================================
// APPLICATION
// ============================================
define('APP_NAME', 'GPS Payments - Member Association System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/gpspayments');
define('APP_TIMEZONE', 'Africa/Accra');

// ============================================
// PAYMENT METHODS
// ============================================
define('PAYMENT_METHODS', [
    'Cash' => 'Cash (In-Person)',
    'Mobile Money' => 'Mobile Money (MTN/Vodafone)',
    'Bank Transfer' => 'Bank Transfer',
    'USSD' => 'USSD',
    'Card' => 'Credit/Debit Card'
]);

// ============================================
// PAYMENT STATUS
// ============================================
define('PAYMENT_STATUS', [
    'Pending_Entry' => 'Pending Entry',
    'Pending_Verification' => 'Awaiting Verification',
    'Pending_Reconciliation' => 'Awaiting Reconciliation',
    'Verified' => 'Verified',
    'Confirmed' => 'Confirmed',
    'Failed' => 'Failed',
    'Timeout' => 'Timeout',
    'Refunded' => 'Refunded'
]);

define('PAYMENT_STATUS_FINAL', ['Confirmed', 'Failed', 'Timeout', 'Refunded']);
define('PAYMENT_STATUS_PENDING', ['Pending_Entry', 'Pending_Verification', 'Pending_Reconciliation']);

// ============================================
// COLLECTION TYPES
// ============================================
define('COLLECTION_TYPES', [
    'Recurring' => 'Recurring (Monthly/Quarterly/Yearly)',
    'One-time' => 'One-time Payment',
    'Voluntary' => 'Voluntary Contribution'
]);

define('COLLECTION_FREQUENCIES', [
    'Monthly' => 'Monthly',
    'Quarterly' => 'Every 3 Months',
    'Yearly' => 'Annual',
    'One-time' => 'One-time',
    'Custom' => 'Custom'
]);

// ============================================
// MEMBER STATUS
// ============================================
define('MEMBER_STATUS', [
    'Active' => 'Active',
    'Inactive' => 'Inactive',
    'Suspended' => 'Suspended'
]);

// ============================================
// USER ROLES
// ============================================
define('USER_ROLES', [
    'Administrator' => 'Administrator',
    'Treasurer' => 'Treasurer / Finance Officer',
    'Secretary' => 'Secretary',
    'Auditor' => 'Auditor / Chairman',
    'Member' => 'Member'
]);

// Role-based permissions
define('ROLE_PERMISSIONS', [
    'Administrator' => [
        'manage_users',
        'manage_settings',
        'register_members',
        'record_payments',
        'verify_payments',
        'reconcile_payments',
        'generate_reports',
        'manage_backups',
        'view_audit_logs'
    ],
    'Treasurer' => [
        'record_payments',
        'verify_payments',
        'reconcile_payments',
        'generate_reports',
        'view_members',
        'view_collections'
    ],
    'Secretary' => [
        'register_members',
        'update_members',
        'view_members',
        'view_collections',
        'view_payment_status',
        'send_notices',
        'generate_reports'
    ],
    'Auditor' => [
        'view_members',
        'view_payments',
        'view_reports',
        'view_audit_logs'
    ],
    'Member' => [
        'view_own_dues',
        'view_own_arrears',
        'view_own_payments',
        'download_receipts'
    ]
]);

// ============================================
// VERIFICATION & VALIDATION
// ============================================
define('DUPLICATE_DETECTION_HOURS', 24);
define('PAYMENT_TIMEOUT_MINUTES', 60);
define('PAYMENT_RETRY_INTERVAL', 5);
define('BURST_DETECTION_LIMIT', 3);
define('BURST_DETECTION_WINDOW_MINUTES', 60);
define('OVERPAYMENT_THRESHOLD_PERCENT', 50);

// ============================================
// NOTIFICATION TEMPLATES
// ============================================
define('NOTIFICATION_TEMPLATES', [
    'Payment_Received' => 'GHS {amount} received for {collection_item}. Ref: {reference}. Thank you.',
    'Arrears_Alert' => 'Arrears alert: GHS {amount} due for {collection_item}. Please pay by {due_date}.',
    'Overpayment' => 'Overpayment of GHS {amount} received. Credited to your account.',
    'Payment_Confirmed' => 'Payment confirmed. Receipt {receipt_number} available online.',
    'Refund_Initiated' => 'Refund of GHS {amount} being processed to your account.',
    'Collection_Assigned' => '{collection_item} due: GHS {amount}. Payment methods: {methods}'
]);

// ============================================
// AUDIT LOG ACTIONS
// ============================================
define('AUDIT_ACTIONS', [
    'PAYMENT_RECORDED' => 'Payment Recorded',
    'PAYMENT_VERIFIED' => 'Payment Verified',
    'PAYMENT_CONFIRMED' => 'Payment Confirmed',
    'PAYMENT_FAILED' => 'Payment Failed',
    'PAYMENT_REFUNDED' => 'Payment Refunded',
    'RECONCILIATION_OPENED' => 'Reconciliation Opened',
    'RECONCILIATION_CLOSED' => 'Reconciliation Closed',
    'MEMBER_REGISTERED' => 'Member Registered',
    'MEMBER_UPDATED' => 'Member Updated',
    'COLLECTION_CREATED' => 'Collection Created',
    'COLLECTION_ASSIGNED' => 'Collection Assigned',
    'USER_LOGIN' => 'User Login',
    'USER_LOGOUT' => 'User Logout'
]);

// ============================================
// REPORT TYPES
// ============================================
define('REPORT_TYPES', [
    'Daily_Collections' => 'Daily Collections Report',
    'Monthly_Collections' => 'Monthly Collections Report',
    'Yearly_Collections' => 'Yearly Collections Report',
    'Member_Statement' => 'Member Payment Statement',
    'Arrears_Report' => 'Arrears / Defaulters Report',
    'Donation_Report' => 'Donations Report',
    'Cash_Collection' => 'Cash Collection Report',
    'Mobile_Money' => 'Mobile Money Collection Report',
    'Bank_Transfer' => 'Bank Transfer Report',
    'Collector_Performance' => 'Collector Performance Report'
]);

// ============================================
// EXPORT FORMATS
// ============================================
define('EXPORT_FORMATS', [
    'PDF' => 'PDF',
    'Excel' => 'Excel (XLS)',
    'CSV' => 'CSV',
    'Print' => 'Print'
]);

// ============================================
// CURRENCY
// ============================================
define('CURRENCY_CODE', 'GHS');
define('CURRENCY_SYMBOL', '₵');
define('DECIMAL_PLACES', 2);

// ============================================
// PAGINATION
// ============================================
define('ITEMS_PER_PAGE', 20);
define('ITEMS_PER_PAGE_REPORT', 50);

// ============================================
// FILE UPLOADS
// ============================================
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads/');

// ============================================
// SESSION
// ============================================
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes
define('REMEMBER_ME_TIMEOUT', 30 * 24 * 60 * 60); // 30 days

// ============================================
// SECURITY
// ============================================
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// ============================================
// API KEYS & INTEGRATIONS
// ============================================
define('PAYMENT_GATEWAY_TIMEOUT', 30); // seconds
define('SMS_GATEWAY_API_KEY', $_ENV['SMS_API_KEY'] ?? null);
define('SMS_GATEWAY_URL', $_ENV['SMS_GATEWAY_URL'] ?? null);
define('MOMO_CALLBACK_SECRET', $_ENV['MOMO_CALLBACK_SECRET'] ?? null);
