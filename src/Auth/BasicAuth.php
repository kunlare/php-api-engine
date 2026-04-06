<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Auth;

use Kunlare\PhpCrudApi\Api\Request;
use Kunlare\PhpCrudApi\Database\Connection;
use Kunlare\PhpCrudApi\Config\Config;

/**
 * HTTP Basic Authentication strategy.
 */
class BasicAuth implements AuthInterface
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Config Configuration */
    private Config $config;

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
     * Authenticate a request using Basic Auth.
     *
     * @param Request $request The HTTP request
     * @return array<string, mixed>|null User data or null
     */
    public function authenticate(Request $request): ?array
    {
        $credentials = $request->getBasicAuth();
        if ($credentials === null) {
            return null;
        }

        $usersTable = $this->config->getString('USERS_TABLE', 'users');
        $user = $this->connection->fetch(
            "SELECT * FROM `{$usersTable}` WHERE (email = ? OR username = ?) AND is_active = 1",
            [$credentials['username'], $credentials['username']]
        );

        if ($user === null) {
            return null;
        }

        if (!password_verify($credentials['password'], $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    /**
     * Validate Basic Auth credentials string.
     *
     * @param string $credentials Base64-encoded username:password
     * @return bool True if valid
     */
    public function validate(string $credentials): bool
    {
        $decoded = base64_decode($credentials, true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return false;
        }

        [$username, $password] = explode(':', $decoded, 2);

        $usersTable = $this->config->getString('USERS_TABLE', 'users');
        $user = $this->connection->fetch(
            "SELECT password_hash FROM `{$usersTable}` WHERE (email = ? OR username = ?) AND is_active = 1",
            [$username, $username]
        );

        if ($user === null) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }
}
