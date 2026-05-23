<?php
declare(strict_types=1);

namespace App;

/**
 * Lightweight HTTP router for exact-match routes.
 */
class Router {
    /**
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function dispatch(string $method, string $path): bool {
        $method = strtoupper($method);
        $normalizedPath = $this->normalizePath($path);

        if (!isset($this->routes[$method][$normalizedPath])) {
            return false;
        }

        ($this->routes[$method][$normalizedPath])();
        return true;
    }

    private function addRoute(string $method, string $path, callable $handler): void {
        $normalizedPath = $this->normalizePath($path);
        $this->routes[$method][$normalizedPath] = $handler;
    }

    private function normalizePath(string $path): string {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}

