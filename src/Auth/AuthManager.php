<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Auth;

use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;

/**
 * Factory for selecting authentication strategy based on configuration.
 */
class AuthManager
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Config Configuration */
    private Config $config;

    /** @var AuthInterface|null Cached auth strategy */
    private ?AuthInterface $strategy = null;

    /**
     * @param Connection $connection Database connection
     * @param Config $config Configuration
     */
    public function __construct(Connection $connection, Config $config)
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    /**
     * Get the configured authentication strategy.
     *
     * @return AuthInterface
     * @throws AuthException If the configured method is unknown
     */
    public function getAuthStrategy(): AuthInterface
    {
        if ($this->strategy !== null) {
            return $this->strategy;
        }

        $method = strtolower($this->config->getString('AUTH_METHOD', 'jwt'));

        $this->strategy = match ($method) {
            'basic' => new BasicAuth($this->connection, $this->config),
            'apikey' => new ApiKeyAuth($this->connection, $this->config),
            'jwt' => new JwtAuth($this->connection, $this->config),
            default => throw new AuthException("Unknown authentication method: {$method}", 500),
        };

        return $this->strategy;
    }

    /**
     * Get the JWT auth instance (needed for token generation).
     *
     * @return JwtAuth
     */
    public function getJwtAuth(): JwtAuth
    {
        return new JwtAuth($this->connection, $this->config);
    }

    /**
     * Get the API Key auth instance (needed for key generation).
     *
     * @return ApiKeyAuth
     */
    public function getApiKeyAuth(): ApiKeyAuth
    {
        return new ApiKeyAuth($this->connection, $this->config);
    }
}
