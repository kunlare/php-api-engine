<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Database;

use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\DatabaseException;
use Kunlare\PhpApiEngine\Tests\TestCase;

class ConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Connection::resetInstance();
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        parent::tearDown();
    }

    public function testGetInstanceWithoutConfigThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Config is required');

        Connection::getInstance();
    }

    public function testResetInstanceAllowsNewConnection(): void
    {
        Connection::resetInstance();

        // After reset, getInstance without config should throw
        $this->expectException(DatabaseException::class);
        Connection::getInstance();
    }

    public function testConnectionFailsWithInvalidCredentials(): void
    {
        $config = $this->createConfig([
            'DB_HOST' => 'invalid-host-that-does-not-exist',
            'DB_PORT' => '9999',
            'DB_NAME' => 'nonexistent',
            'DB_USER' => 'nobody',
            'DB_PASSWORD' => 'wrong',
        ]);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Database connection failed');

        Connection::getInstance($config);
    }
}
