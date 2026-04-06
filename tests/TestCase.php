<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Tests;

use Kunlare\PhpCrudApi\Config\Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with common helpers.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Create a mock Config with given values.
     *
     * @param array<string, mixed> $values Configuration values
     * @return Config
     */
    protected function createConfig(array $values = []): Config
    {
        $defaults = [
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_NAME' => 'test_db',
            'DB_USER' => 'root',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
            'API_VERSION' => 'v1',
            'API_BASE_PATH' => '/api',
            'API_DEBUG' => 'false',
            'AUTH_METHOD' => 'jwt',
            'JWT_SECRET' => 'test-secret-key-that-is-at-least-32-characters-long',
            'JWT_ALGORITHM' => 'HS256',
            'JWT_EXPIRATION' => '3600',
            'JWT_REFRESH_EXPIRATION' => '604800',
            'USERS_TABLE' => 'users',
            'API_KEYS_TABLE' => 'api_keys',
            'API_KEY_PREFIX' => 'ak_test_',
            'API_KEY_LENGTH' => '32',
            'API_KEY_HASH_ALGO' => 'sha256',
            'AUTO_SETUP' => 'true',
            'FIRST_USER_IS_ADMIN' => 'true',
            'ENABLE_CORS' => 'true',
            'ALLOWED_ORIGINS' => '*',
            'ALLOWED_METHODS' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'ALLOWED_HEADERS' => 'Content-Type,Authorization,X-API-Key',
            'MIN_PASSWORD_LENGTH' => '8',
            'REQUIRE_SPECIAL_CHARS' => 'true',
        ];

        $merged = array_merge($defaults, $values);

        // Set environment variables for Config to read
        foreach ($merged as $key => $value) {
            $_ENV[$key] = $value;
        }

        $config = new Config();
        return $config;
    }

    /**
     * Clean up environment variables after test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
