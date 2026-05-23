<?php
/**
 * Application Settings
 *
 * Global settings that can be configured per association
 */

date_default_timezone_set(APP_TIMEZONE);

// ============================================
// ERROR HANDLING
// ============================================
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/logs/error.log');
}

// ============================================
// SESSION SETTINGS
// ============================================
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', REMEMBER_ME_TIMEOUT);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_httponly', true);
ini_set('session.cookie_samesite', 'Lax');

// ============================================
// SECURITY HEADERS
// ============================================
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Set headers on every request
set_security_headers();

// ============================================
// CORS SETTINGS
// ============================================
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// ============================================
// PAYMENT GATEWAY SETTINGS
// ============================================
$PAYMENT_GATEWAY_CONFIG = [
    'momo' => [
        'enabled' => !empty($_ENV['MOMO_API_KEY']),
        'api_key' => $_ENV['MOMO_API_KEY'] ?? null,
        'callback_secret' => $_ENV['MOMO_CALLBACK_SECRET'] ?? null,
        'timeout' => PAYMENT_GATEWAY_TIMEOUT,
        'retry_attempts' => 3,
        'retry_delay' => 5000 // milliseconds
    ],
    'bank_transfer' => [
        'enabled' => true,
        'requires_proof' => true,
        'manual_reconciliation' => true
    ],
    'ussd' => [
        'enabled' => false,
        'provider_api' => null,
        'timeout' => 120 // seconds
    ],
    'card' => [
        'enabled' => false,
        'gateway' => null
    ]
];

// ============================================
// NOTIFICATION SETTINGS
// ============================================
$NOTIFICATION_CONFIG = [
    'sms' => [
        'enabled' => !empty($_ENV['SMS_API_KEY']),
        'gateway_url' => $_ENV['SMS_GATEWAY_URL'] ?? null,
        'api_key' => $_ENV['SMS_API_KEY'] ?? null,
        'sender_id' => $_ENV['SMS_SENDER_ID'] ?? 'GPS_PAYMENTS',
        'timeout' => 10
    ],
    'email' => [
        'enabled' => !empty($_ENV['MAIL_HOST']),
        'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? null,
        'port' => $_ENV['MAIL_PORT'] ?? 587,
        'username' => $_ENV['MAIL_USERNAME'] ?? null,
        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
        'from' => $_ENV['MAIL_FROM'] ?? 'noreply@gpspayments.local'
    ]
];

// ============================================
// LOGGING SETTINGS
// ============================================
$LOG_CONFIG = [
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'file',
    'level' => $_ENV['LOG_LEVEL'] ?? 'info',
    'file_path' => dirname(__DIR__) . '/logs/',
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'retention_days' => 30,
    'log_queries' => APP_ENV === 'development',
    'log_payments' => true,
    'log_audit_trail' => true
];

// ============================================
// VERIFICATION RULES
// ============================================
$VERIFICATION_RULES = [
    'duplicate_detection' => [
        'enabled' => true,
        'window_hours' => DUPLICATE_DETECTION_HOURS,
        'action' => 'flag_for_review' // flag_for_review, reject, allow_with_warning
    ],
    'amount_validation' => [
        'enabled' => true,
        'allow_overpayment' => true,
        'overpayment_threshold' => OVERPAYMENT_THRESHOLD_PERCENT,
        'allow_partial' => true
    ],
    'fraud_detection' => [
        'enabled' => true,
        'burst_detection' => true,
        'burst_limit' => BURST_DETECTION_LIMIT,
        'burst_window_minutes' => BURST_DETECTION_WINDOW_MINUTES,
        'outlier_detection' => true,
        'outlier_threshold' => 200 // percent of average
    ]
];

// ============================================
// RECONCILIATION SETTINGS
// ============================================
$RECONCILIATION_CONFIG = [
    'auto_create_batches' => false,
    'batch_creation_time' => '23:59', // End of day
    'auto_reconcile_digital' => true,
    'cash_reconciliation_required' => true,
    'flag_discrepancies_above' => 50, // GHS
    'max_time_for_reconciliation' => 48 // hours
];

// ============================================
// RECEIPT SETTINGS
// ============================================
$RECEIPT_CONFIG = [
    'auto_generate' => true,
    'format' => 'pdf',
    'include_qr_code' => true,
    'include_logo' => true,
    'include_signature_line' => true,
    'storage_path' => dirname(__DIR__) . '/public/uploads/receipts/',
    'retention_days' => 0 // Keep all
];

// ============================================
// FEATURE FLAGS
// ============================================
$FEATURE_FLAGS = [
    'enable_member_portal' => true,
    'enable_card_payments' => false,
    'enable_ussd_payments' => false,
    'enable_sms_reminders' => true,
    'enable_email_receipts' => true,
    'enable_donation_tracking' => true,
    'enable_member_groups' => true,
    'require_payment_verification' => true,
    'require_payment_reconciliation' => true
];

// Export settings for use throughout application
$GLOBALS['payment_gateway_config'] = $PAYMENT_GATEWAY_CONFIG;
$GLOBALS['notification_config'] = $NOTIFICATION_CONFIG;
$GLOBALS['log_config'] = $LOG_CONFIG;
$GLOBALS['verification_rules'] = $VERIFICATION_RULES;
$GLOBALS['reconciliation_config'] = $RECONCILIATION_CONFIG;
$GLOBALS['receipt_config'] = $RECEIPT_CONFIG;
$GLOBALS['feature_flags'] = $FEATURE_FLAGS;

/**
 * Get a configuration value
 *
 * @param string $key Configuration key (dot notation)
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function config($key, $default = null) {
    global $GLOBALS;

    $parts = explode('.', $key);
    $config = $GLOBALS[array_shift($parts)] ?? null;

    if ($config === null) {
        return $default;
    }

    foreach ($parts as $part) {
        $config = $config[$part] ?? null;
        if ($config === null) {
            return $default;
        }
    }

    return $config;
}
