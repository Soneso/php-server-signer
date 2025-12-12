<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Handler;

use Closure;
use InvalidArgumentException;

/**
 * Simple HTTP router with support for routing, CORS, and bearer token authentication.
 */
final class Router
{
    /**
     * @var array<string, array<string, Closure>> Route handlers indexed by method and path
     */
    private array $routes = [];

    private ?string $bearerToken = null;

    /**
     * @var array<string> Paths that require authentication
     */
    private array $authenticatedPaths = [];

    /**
     * Set the bearer token for authentication.
     *
     * @param string $token Bearer token
     * @return self
     */
    public function setBearerToken(string $token): self
    {
        $this->bearerToken = $token;
        return $this;
    }

    /**
     * Register a GET route.
     *
     * @param string $path Route path
     * @param Closure $handler Request handler
     * @return self
     */
    public function get(string $path, Closure $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $path Route path
     * @param Closure $handler Request handler
     * @return self
     */
    public function post(string $path, Closure $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a route for any HTTP method.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path
     * @param Closure $handler Request handler
     * @return self
     */
    public function addRoute(string $method, string $path, Closure $handler): self
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }
        $this->routes[$method][$path] = $handler;
        return $this;
    }

    /**
     * Mark a path as requiring authentication.
     *
     * @param string $path Route path
     * @return self
     */
    public function requireAuth(string $path): self
    {
        if (!in_array($path, $this->authenticatedPaths, true)) {
            $this->authenticatedPaths[] = $path;
        }
        return $this;
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @return void
     */
    public function handle(): void
    {
        // Set CORS headers
        $this->setCorsHeaders();

        // Handle OPTIONS preflight request
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            $this->sendResponse(200, null);
            return;
        }

        // Get request path
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($path === false) {
            $path = '/';
        }

        // Check if route exists
        if (!isset($this->routes[$method][$path])) {
            $this->sendError(404, 'Not Found');
            return;
        }

        // Check authentication if required
        if (in_array($path, $this->authenticatedPaths, true)) {
            if (!$this->authenticate()) {
                $this->sendError(401, 'Unauthenticated');
                return;
            }
        }

        // Execute route handler
        try {
            $handler = $this->routes[$method][$path];
            $handler();
        } catch (\Throwable $e) {
            error_log("Router error: {$e->getMessage()}");
            $this->sendError(500, 'Internal Server Error');
        }
    }

    /**
     * Authenticate the request using bearer token.
     *
     * @return bool True if authenticated, false otherwise
     */
    private function authenticate(): bool
    {
        if ($this->bearerToken === null) {
            return false;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader === '') {
            return false;
        }

        // Parse "Bearer <token>" format
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = substr($authHeader, 7);
        return hash_equals($this->bearerToken, $token);
    }

    /**
     * Set CORS headers for cross-origin requests.
     *
     * @return void
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Send a JSON response.
     *
     * @param int $statusCode HTTP status code
     * @param mixed $data Response data
     * @return void
     */
    public function sendResponse(int $statusCode, mixed $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        if ($data !== null) {
            echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        exit;
    }

    /**
     * Send an error response.
     *
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @return void
     */
    public function sendError(int $statusCode, string $message): void
    {
        $this->sendResponse($statusCode, ['error' => $message]);
    }

    /**
     * Get the request body as a decoded JSON object.
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException If the body is not valid JSON
     */
    public function getJsonBody(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            throw new InvalidArgumentException('Request body is empty');
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON body: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Request body must be a JSON object');
        }

        return $data;
    }

    /**
     * Log a message to stderr.
     *
     * @param string $message Message to log
     * @return void
     */
    public static function log(string $message): void
    {
        fwrite(\STDERR, date('[Y-m-d H:i:s] ') . $message . PHP_EOL);
    }
}
