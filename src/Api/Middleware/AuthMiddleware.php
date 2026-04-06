<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Api\Middleware;

use Kunlare\PhpCrudApi\Api\Request;
use Kunlare\PhpCrudApi\Api\Response;
use Kunlare\PhpCrudApi\Auth\AuthManager;
use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Exceptions\AuthException;

/**
 * Authentication middleware that validates requests before routing.
 */
class AuthMiddleware
{
    /** @var AuthManager Authentication manager */
    private AuthManager $authManager;

    /** @var Config Configuration */
    private Config $config;

    /** @var array<string> Public routes that bypass authentication */
    private array $publicRoutes = [
        'POST:/auth/login',
        'POST:/auth/register',
        'OPTIONS:*',
    ];

    /**
     * @param AuthManager $authManager Authentication manager
     * @param Config $config Configuration
     */
    public function __construct(AuthManager $authManager, Config $config)
    {
        $this->authManager = $authManager;
        $this->config = $config;
    }

    /**
     * Handle the authentication check.
     *
     * @param Request $request The HTTP request
     * @return bool True if request should proceed
     * @throws AuthException
     */
    public function handle(Request $request): bool
    {
        // OPTIONS requests are always allowed (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return true;
        }

        // Check if the route is public
        if ($this->isPublicRoute($request)) {
            return true;
        }

        // Authenticate the request
        // If X-API-Key header is present, try API key auth first
        $user = null;
        if ($request->getApiKey()) {
            $apiKeyAuth = $this->authManager->getApiKeyAuth();
            $user = $apiKeyAuth->authenticate($request);
        }
        // Fallback to configured strategy (JWT/Basic)
        if ($user === null) {
            $auth = $this->authManager->getAuthStrategy();
            $user = $auth->authenticate($request);
        }

        if ($user === null) {
            throw new AuthException('Unauthorized: Invalid or missing credentials', 401);
        }

        // Inject user info into request
        $request->setUser($user);
        return true;
    }

    /**
     * Check if a route is public (no auth required).
     *
     * @param Request $request The HTTP request
     * @return bool True if public
     */
    private function isPublicRoute(Request $request): bool
    {
        $method = $request->getMethod();
        $basePath = $this->config->getString('API_BASE_PATH', '/api');
        $version = $this->config->getString('API_VERSION', 'v1');
        $prefix = rtrim($basePath, '/') . '/' . $version;

        // Get the path relative to API prefix
        $uri = $request->getUri();
        $relativePath = $uri;
        if (str_starts_with($uri, $prefix)) {
            $relativePath = substr($uri, strlen($prefix));
        }

        foreach ($this->publicRoutes as $route) {
            [$routeMethod, $routePath] = explode(':', $route, 2);

            if ($routePath === '*') {
                if ($routeMethod === $method || $routeMethod === '*') {
                    return true;
                }
                continue;
            }

            if (($routeMethod === $method || $routeMethod === '*') && $relativePath === $routePath) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a public route.
     *
     * @param string $method HTTP method
     * @param string $path Route path
     */
    public function addPublicRoute(string $method, string $path): void
    {
        $this->publicRoutes[] = strtoupper($method) . ':' . $path;
    }
}
