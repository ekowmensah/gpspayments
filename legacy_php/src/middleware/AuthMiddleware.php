<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Response;
use App\Utils\Logger;

/**
 * Authentication Middleware
 * Verifies user is logged in and session is valid
 */
class AuthMiddleware {
    private Logger $logger;
    
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Handle authentication
     * Returns true if authenticated, false otherwise
     */
    public function handle(): bool {
        // Check if user is logged in
        if (empty($_SESSION['user_id'])) {
            $this->logger->warning('Unauthorized access attempt');
            $this->respondUnauthenticated('Please log in first');
            return false;
        }
        
        // Check session timeout
        if ($this->isSessionExpired()) {
            $this->logger->warning('Session expired', ['user_id' => $_SESSION['user_id']]);
            session_destroy();
            $this->respondUnauthenticated('Session expired. Please log in again');
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check if session has expired
     */
    private function isSessionExpired(): bool {
        $timeout = SESSION_TIMEOUT;
        $last_activity = $_SESSION['last_activity'] ?? time();
        
        if ((time() - $last_activity) > $timeout) {
            return true;
        }
        
        return false;
    }

    private function respondUnauthenticated(string $message): void {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $wantsJson = str_contains($accept, 'application/json');

        if (!$isAjax && !$wantsJson) {
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            Response::redirect($basePath . '/auth/login');
        }

        Response::unauthorized($message);
    }
}
?>
