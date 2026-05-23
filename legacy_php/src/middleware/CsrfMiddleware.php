<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Request;
use App\Utils\Response;
use App\Utils\SecurityHelper;

/**
 * CSRF middleware for state-changing requests.
 */
class CsrfMiddleware {
    public function handle(Request $request): void {
        if (!$this->isProtectedMethod($request->method())) {
            return;
        }

        $token = $this->extractToken($request);
        if ($token === '' || !SecurityHelper::verifyCsrfToken($token)) {
            Response::error('Invalid CSRF token', 419);
        }
    }

    private function isProtectedMethod(string $method): bool {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function extractToken(Request $request): string {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($headerToken !== '') {
            return (string)$headerToken;
        }

        $bodyToken = $request->input('csrf_token', '');
        return is_string($bodyToken) ? $bodyToken : '';
    }
}

