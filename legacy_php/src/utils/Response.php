<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * HTTP Response Handler
 * Handles JSON responses and formatting
 */
class Response {
    
    /**
     * Send JSON success response
     */
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send success response
     */
    public static function success(array $data = [], string $message = 'Success'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Send error response
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError(array $errors): void {
        self::json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::json([
            'success' => false,
            'message' => $message
        ], 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void {
        self::json([
            'success' => false,
            'message' => $message
        ], 403);
    }
    
    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Not Found'): void {
        self::json([
            'success' => false,
            'message' => $message
        ], 404);
    }
    
    /**
     * Send server error response
     */
    public static function serverError(string $message = 'Internal Server Error'): void {
        self::json([
            'success' => false,
            'message' => $message
        ], 500);
    }
    
    /**
     * Redirect to URL
     */
    public static function redirect(string $url): void {
        header("Location: $url");
        exit;
    }
    
    /**
     * Render HTML view
     */
    public static function view(string $path, array $data = []): void {
        extract($data);
        $viewFile = __DIR__ . "/../../views/{$path}.php";
        if (!file_exists($viewFile)) {
            self::notFound("View not found: {$path}");
        }

        require_once $viewFile;
        exit;
    }
}
?>
