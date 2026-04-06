<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Api;

/**
 * HTTP request wrapper providing access to request data.
 */
class Request
{
    /** @var string HTTP method */
    private string $method;

    /** @var string Request URI */
    private string $uri;

    /** @var array<string, string> Request headers */
    private array $headers;

    /** @var array<string, mixed> Query parameters */
    private array $queryParams;

    /** @var array<string, mixed> Path parameters (extracted from route) */
    private array $pathParams = [];

    /** @var string|null Raw body content */
    private ?string $rawBody = null;

    /** @var array<string, mixed>|null Decoded JSON body */
    private ?array $jsonBody = null;

    /** @var array<string, mixed>|null Authenticated user info */
    private ?array $user = null;

    /**
     * Create a new Request instance from PHP globals.
     */
    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri();
        $this->headers = $this->parseHeaders();
        $this->queryParams = $_GET;
    }

    /**
     * Get the HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the request URI (path only, no query string).
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get all request headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     *
     * @param string $name Header name (case-insensitive)
     * @param string|null $default Default value
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Get the raw request body.
     */
    public function getBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    /**
     * Get the decoded JSON body.
     *
     * @return array<string, mixed>
     */
    public function getJson(): array
    {
        if ($this->jsonBody === null) {
            $body = $this->getBody();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonBody = [];
            }
        }
        return $this->jsonBody;
    }

    /**
     * Get all query parameters.
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Get a specific query parameter.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    /**
     * Get all path parameters.
     *
     * @return array<string, mixed>
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * Get a specific path parameter.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value
     */
    public function getPathParam(string $name, mixed $default = null): mixed
    {
        return $this->pathParams[$name] ?? $default;
    }

    /**
     * Set path parameters (called by router).
     *
     * @param array<string, mixed> $params Path parameters
     */
    public function setPathParams(array $params): void
    {
        $this->pathParams = $params;
    }

    /**
     * Get the authenticated user info.
     *
     * @return array<string, mixed>|null
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * Set the authenticated user info (called by auth middleware).
     *
     * @param array<string, mixed> $user User data
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /**
     * Extract Basic Auth credentials from Authorization header.
     *
     * @return array{username: string, password: string}|null
     */
    public function getBasicAuth(): ?array
    {
        $auth = $this->getHeader('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$username, $password] = explode(':', $decoded, 2);
                return ['username' => $username, 'password' => $password];
            }
        }
        return null;
    }

    /**
     * Extract API Key from X-API-Key header.
     */
    public function getApiKey(): ?string
    {
        return $this->getHeader('X-API-Key');
    }

    /**
     * Parse the request URI from server variables.
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return '/' . trim($uri, '/');
    }

    /**
     * Parse request headers from server variables.
     *
     * @return array<string, string>
     */
    private function parseHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                return $allHeaders;
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
