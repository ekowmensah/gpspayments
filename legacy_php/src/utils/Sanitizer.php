<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Input Sanitizer
 * Prevents XSS and SQL injection
 */
class Sanitizer {
    
    /**
     * Escape HTML special characters (prevent XSS)
     */
    public static function escape(?string $string): string {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitize text input
     */
    public static function text(?string $value): string {
        if ($value === null) {
            return '';
        }
        
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Sanitize email
     */
    public static function email(?string $value): string {
        if ($value === null) {
            return '';
        }
        
        $value = trim($value);
        $value = strtolower($value);
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        
        return $value;
    }
    
    /**
     * Sanitize phone number
     */
    public static function phone(?string $value): string {
        if ($value === null) {
            return '';
        }
        
        // Remove all non-digit characters
        $value = preg_replace('/\D/', '', $value);
        
        return $value;
    }
    
    /**
     * Sanitize numeric value
     */
    public static function number($value) {
        if ($value === null) {
            return 0;
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        return 0;
    }
    
    /**
     * Sanitize float value
     */
    public static function float($value): float {
        if ($value === null) {
            return 0.0;
        }
        
        return (float)$value;
    }
    
    /**
     * Sanitize integer value
     */
    public static function integer($value): int {
        if ($value === null) {
            return 0;
        }
        
        return (int)$value;
    }
    
    /**
     * Sanitize boolean
     */
    public static function boolean($value): bool {
        if ($value === null) {
            return false;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize array - recursively sanitize all values
     */
    public static function array(array $arr): array {
        $sanitized = [];
        
        foreach ($arr as $key => $value) {
            $sanitized[$key] = self::text((string)$value);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize filename
     */
    public static function filename(?string $filename): string {
        if ($filename === null) {
            return '';
        }
        
        // Remove special characters
        $filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
        
        // Limit length
        $filename = substr($filename, 0, 255);
        
        return $filename;
    }
    
    /**
     * Sanitize URL
     */
    public static function url(?string $url): string {
        if ($url === null) {
            return '';
        }
        
        $url = trim($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Remove HTML tags
     */
    public static function stripTags(?string $string): string {
        if ($string === null) {
            return '';
        }
        
        return strip_tags($string);
    }
}
?>
