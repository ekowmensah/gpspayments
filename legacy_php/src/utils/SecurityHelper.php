<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Security Helper
 * Password hashing, token generation, CSRF protection
 */
class SecurityHelper {
    
    /**
     * Hash a password using Argon2id
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 19456,
            'time_cost' => 2,
            'threads' => 1,
        ]);
    }
    
    /**
     * Verify password against hash
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password needs rehashing
     */
    public static function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 19456,
            'time_cost' => 2,
            'threads' => 1,
        ]);
    }
    
    /**
     * Generate a random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token from session
     */
    public static function getCsrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(string $token): bool {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate API token
     */
    public static function generateApiToken(): string {
        return 'api_' . self::generateToken();
    }
    
    /**
     * Generate unique reference number
     */
    public static function generateReference(string $prefix = 'GPS'): string {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    /**
     * Generate receipt number
     */
    public static function generateReceiptNumber(): string {
        return 'GPS-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
    
    /**
     * Hash sensitive data
     */
    public static function hashData(string $data): string {
        return hash('sha256', $data);
    }
    
    /**
     * Compare two strings securely
     */
    public static function constantTimeEquals(string $a, string $b): bool {
        return hash_equals($a, $b);
    }
    
    /**
     * Generate session ID
     */
    public static function generateSessionId(): string {
        return 'sess_' . self::generateToken();
    }
    
    /**
     * Check if password is strong
     */
    public static function isStrongPassword(string $password): bool {
        // At least 8 characters
        if (strlen($password) < 8) {
            return false;
        }
        
        // Must contain uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Must contain lowercase
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Must contain numbers
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Must contain special characters
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Rate limit check (simple in-memory)
     */
    public static function checkRateLimit(string $key, int $limit = 5, int $window = 60): bool {
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        $key_data = &$_SESSION['rate_limit'][$key];
        
        if (!isset($key_data)) {
            $key_data = [];
        }
        
        // Remove old entries
        $key_data = array_filter($key_data, function($time) use ($now, $window) {
            return ($now - $time) < $window;
        });
        
        // Check limit
        if (count($key_data) >= $limit) {
            return false;
        }
        
        // Add current request
        $key_data[] = $now;
        $_SESSION['rate_limit'][$key] = $key_data;
        
        return true;
    }
}
?>
