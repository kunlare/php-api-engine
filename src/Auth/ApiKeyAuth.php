<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Auth;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Config\Config;

/**
 * API Key authentication strategy.
 */
class ApiKeyAuth implements AuthInterface
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
     * Authenticate a request using API Key.
     *
     * @param Request $request The HTTP request
     * @return array<string, mixed>|null User data or null
     */
    public function authenticate(Request $request): ?array
    {
        $apiKey = $request->getApiKey();
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $algo = $this->config->getString('API_KEY_HASH_ALGO', 'sha256');
        $keyHash = hash($algo, $apiKey);

        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');
        $usersTable = $this->config->getString('USERS_TABLE', 'users');

        $result = $this->connection->fetch(
            "SELECT ak.*, u.id as user_id, u.username, u.email, u.role, u.is_active
             FROM `{$apiKeysTable}` ak
             JOIN `{$usersTable}` u ON ak.user_id = u.id
             WHERE ak.key_hash = ? AND ak.is_active = 1 AND u.is_active = 1
             AND (ak.expires_at IS NULL OR ak.expires_at > NOW())",
            [$keyHash]
        );

        if ($result === null) {
            return null;
        }

        // Update last_used_at
        $this->connection->execute(
            "UPDATE `{$apiKeysTable}` SET last_used_at = NOW() WHERE id = ?",
            [$result['id']]
        );

        return [
            'id' => $result['user_id'],
            'username' => $result['username'],
            'email' => $result['email'],
            'role' => $result['role'],
            'is_active' => $result['is_active'],
            'api_key_id' => $result['id'],
            'api_key_name' => $result['name'],
        ];
    }

    /**
     * Validate an API key string.
     *
     * @param string $credentials The API key
     * @return bool True if valid
     */
    public function validate(string $credentials): bool
    {
        $algo = $this->config->getString('API_KEY_HASH_ALGO', 'sha256');
        $keyHash = hash($algo, $credentials);

        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');

        $result = $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$apiKeysTable}` WHERE key_hash = ? AND is_active = 1
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$keyHash]
        );

        return (int) $result > 0;
    }

    /**
     * Generate a new API key.
     *
     * @param int $userId User ID
     * @param string $name Key name
     * @param string|null $expiresAt Expiration datetime
     * @param array<string>|null $permissions Allowed permissions
     * @return array{key: string, id: int}
     */
    public function generateKey(
        int $userId,
        string $name = '',
        ?string $expiresAt = null,
        ?array $permissions = null
    ): array {
        $prefix = $this->config->getString('API_KEY_PREFIX', 'ak_live_');
        $length = $this->config->getInt('API_KEY_LENGTH', 32);
        $algo = $this->config->getString('API_KEY_HASH_ALGO', 'sha256');

        $rawKey = $prefix . bin2hex(random_bytes(max(1, $length)));
        $keyHash = hash($algo, $rawKey);

        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');

        $this->connection->execute(
            "INSERT INTO `{$apiKeysTable}` (user_id, key_hash, name, permissions, expires_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                $userId,
                $keyHash,
                $name,
                $permissions !== null ? json_encode($permissions) : null,
                $expiresAt,
            ]
        );

        $id = (int) $this->connection->lastInsertId();

        return ['key' => $rawKey, 'id' => $id];
    }
}
