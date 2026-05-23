<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Request Handler
 * Handles HTTP request data
 */
class Request {
    private array $data = [];
    private string $method;
    
    public function __construct() {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($this->method === 'POST' && isset($_POST['_method'])) {
            $overrideMethod = strtoupper((string)$_POST['_method']);
            if (in_array($overrideMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $overrideMethod;
            }
        }
        
        // Merge GET, POST, and JSON body
        $this->data = $_GET;
        
        if ($this->method === 'POST' || $this->method === 'PUT' || $this->method === 'DELETE') {
            $this->data = array_merge($this->data, $_POST);
            
            // Parse JSON body if content-type is application/json
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $json = json_decode(file_get_contents('php://input'), true);
                if (is_array($json)) {
                    $this->data = array_merge($this->data, $json);
                }
            }
        }
    }
    
    /**
     * Get request method
     */
    public function method(): string {
        return $this->method;
    }
    
    /**
     * Check if request is GET
     */
    public function isGet(): bool {
        return $this->method === 'GET';
    }
    
    /**
     * Check if request is POST
     */
    public function isPost(): bool {
        return $this->method === 'POST';
    }
    
    /**
     * Check if request is PUT
     */
    public function isPut(): bool {
        return $this->method === 'PUT';
    }
    
    /**
     * Check if request is DELETE
     */
    public function isDelete(): bool {
        return $this->method === 'DELETE';
    }
    
    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }
    
    /**
     * Get single input value
     */
    public function input(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Get all input data
     */
    public function all(): array {
        return $this->data;
    }
    
    /**
     * Check if input key exists
     */
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }
    
    /**
     * Get only specified keys
     */
    public function only(array $keys): array {
        $result = [];
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                $result[$key] = $this->data[$key];
            }
        }
        return $result;
    }
    
    /**
     * Get all except specified keys
     */
    public function except(array $keys): array {
        $result = $this->data;
        foreach ($keys as $key) {
            unset($result[$key]);
        }
        return $result;
    }
    
    /**
     * Get uploaded file
     */
    public function file(string $name) {
        return $_FILES[$name] ?? null;
    }
    
    /**
     * Get request URI
     */
    public function uri(): string {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }
    
    /**
     * Get request path (without query string)
     */
    public function path(): string {
        $uri = $this->uri();
        $basePath = $this->basePath();

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        // Support direct /public/* access when app isn't configured with /public as base path.
        if ($basePath === '' && ($uri === '/public' || str_starts_with($uri, '/public/'))) {
            $uri = substr($uri, strlen('/public')) ?: '/';
        }

        $uri = '/' . ltrim((string)$uri, '/');

        return $uri === '//' ? '/' : $uri;
    }

    /**
     * Get application base path derived from script location.
     */
    public function basePath(): string {
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim($basePath, '/');

        return ($basePath === '/' || $basePath === '.') ? '' : $basePath;
    }
    
    /**
     * Get query string parameters
     */
    public function query(string $key = null) {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? null;
    }
    
    /**
     * Get IP address
     */
    public function ip(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get user agent
     */
    public function userAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Get referer
     */
    public function referer(): string {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }
}
?>
