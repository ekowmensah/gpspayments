<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Application Logger
 * Handles all application logging to files
 */
class Logger {
    private string $logDir;
    
    public function __construct() {
        $this->logDir = __DIR__ . '/../../logs/';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log info level message
     */
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log error level message
     */
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Log warning level message
     */
    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log debug level message (development only)
     */
    public function debug(string $message, array $context = []): void {
        if (APP_ENV === 'development') {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log critical errors
     */
    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }
    
    /**
     * Internal logging method
     */
    private function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message";
        
        if (!empty($context)) {
            $log_message .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        $log_message .= "\n";
        
        $log_file = $this->logDir . 'application.log';
        error_log($log_message, 3, $log_file);
        
        // Also log errors to error log
        if ($level === 'ERROR' || $level === 'CRITICAL') {
            $error_file = $this->logDir . 'errors.log';
            error_log($log_message, 3, $error_file);
        }
    }
    
    /**
     * Log payment transaction
     */
    public function logPayment(string $action, array $data): void {
        $log_file = $this->logDir . 'payments.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [PAYMENT] $action\n";
        $log_message .= "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        error_log($log_message, 3, $log_file);
    }
    
    /**
     * Log audit event
     */
    public function logAudit(string $action, int $userId, array $details): void {
        $log_file = $this->logDir . 'audit.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = [
            'timestamp' => $timestamp,
            'action' => $action,
            'user_id' => $userId,
            'details' => $details
        ];
        error_log(json_encode($log_entry) . "\n", 3, $log_file);
    }
}
?>
