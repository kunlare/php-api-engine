<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Api\Middleware;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Config\Config;

/**
 * CORS middleware for handling cross-origin requests.
 */
class CorsMiddleware
{
    /** @var Config Configuration */
    private Config $config;

    /**
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Handle CORS headers.
     *
     * @param Request $request The HTTP request
     * @return bool True to continue, false if preflight handled
     */
    public function handle(Request $request): bool
    {
        if (!$this->config->getBool('ENABLE_CORS', true)) {
            return true;
        }

        $allowedOrigins = $this->config->getString('ALLOWED_ORIGINS', '*');
        $allowedMethods = $this->config->getString('ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $allowedHeaders = $this->config->getString('ALLOWED_HEADERS', 'Content-Type,Authorization,X-API-Key');

        $origin = $request->getHeader('Origin', '');

        if ($allowedOrigins === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && $origin !== null) {
            $origins = array_map('trim', explode(',', $allowedOrigins));
            if (in_array($origin, $origins, true)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Vary: Origin');
            }
        }

        header("Access-Control-Allow-Methods: {$allowedMethods}");
        header("Access-Control-Allow-Headers: {$allowedHeaders}");
        header('Access-Control-Max-Age: 86400');

        // Handle OPTIONS preflight
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);
            return false;
        }

        return true;
    }
}
