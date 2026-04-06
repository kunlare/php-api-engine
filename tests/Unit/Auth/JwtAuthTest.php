<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Auth;

use Kunlare\PhpApiEngine\Auth\JwtAuth;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Tests\TestCase;

class JwtAuthTest extends TestCase
{
    private JwtAuth $jwtAuth;
    private Connection $connection;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->config = $this->createConfig([
            'JWT_SECRET' => 'test-secret-key-that-is-long-enough-for-hs256-algorithm',
            'JWT_ALGORITHM' => 'HS256',
            'JWT_EXPIRATION' => '3600',
            'JWT_REFRESH_EXPIRATION' => '604800',
        ]);

        $this->jwtAuth = new JwtAuth($this->connection, $this->config);
    }

    public function testGenerateTokenReturnsString(): void
    {
        $token = $this->jwtAuth->generateToken([
            'user_id' => 1,
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT has 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testValidateTokenReturnsPayload(): void
    {
        $payload = [
            'user_id' => 1,
            'email' => 'test@example.com',
            'role' => 'admin',
        ];

        $token = $this->jwtAuth->generateToken($payload);
        $decoded = $this->jwtAuth->validateToken($token);

        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('test@example.com', $decoded['email']);
        $this->assertEquals('admin', $decoded['role']);
        $this->assertEquals('access', $decoded['type']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testValidateTokenReturnsFalseForInvalidToken(): void
    {
        $result = $this->jwtAuth->validateToken('invalid.token.here');
        $this->assertFalse($result);
    }

    public function testValidateTokenReturnsFalseForEmptyToken(): void
    {
        $result = $this->jwtAuth->validateToken('');
        $this->assertFalse($result);
    }

    public function testValidateTokenReturnsFalseForTamperedToken(): void
    {
        $token = $this->jwtAuth->generateToken(['user_id' => 1]);

        // Tamper with the token
        $parts = explode('.', $token);
        $parts[1] = base64_encode('{"user_id":999}');
        $tampered = implode('.', $parts);

        $result = $this->jwtAuth->validateToken($tampered);
        $this->assertFalse($result);
    }

    public function testGenerateRefreshTokenHasRefreshType(): void
    {
        $token = $this->jwtAuth->generateRefreshToken([
            'user_id' => 1,
        ]);

        $decoded = $this->jwtAuth->validateToken($token);
        $this->assertIsArray($decoded);
        $this->assertEquals('refresh', $decoded['type']);
        $this->assertEquals(1, $decoded['user_id']);
    }

    public function testTokenExpiration(): void
    {
        // Create config with very short expiration
        $config = $this->createConfig([
            'JWT_SECRET' => 'test-secret-key-that-is-long-enough-for-hs256-algorithm',
            'JWT_EXPIRATION' => '-1', // Already expired
        ]);

        $jwtAuth = new JwtAuth($this->connection, $config);
        $token = $jwtAuth->generateToken(['user_id' => 1]);

        $result = $jwtAuth->validateToken($token);
        $this->assertFalse($result);
    }

    public function testRefreshTokenWithValidRefreshToken(): void
    {
        $user = [
            'id' => 1,
            'username' => 'test',
            'email' => 'test@example.com',
            'role' => 'admin',
        ];

        $this->connection->expects($this->once())
            ->method('fetch')
            ->willReturn($user);

        $refreshToken = $this->jwtAuth->generateRefreshToken(['user_id' => 1]);
        $newToken = $this->jwtAuth->refreshToken($refreshToken);

        $this->assertIsString($newToken);
        $this->assertNotEmpty($newToken);

        // Validate the new token
        $decoded = $this->jwtAuth->validateToken($newToken);
        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded['user_id']);
    }

    public function testRefreshTokenWithAccessTokenReturnsFalse(): void
    {
        $accessToken = $this->jwtAuth->generateToken(['user_id' => 1]);
        $result = $this->jwtAuth->refreshToken($accessToken);
        $this->assertFalse($result);
    }

    public function testRefreshTokenWithInvalidTokenReturnsFalse(): void
    {
        $result = $this->jwtAuth->refreshToken('invalid.token');
        $this->assertFalse($result);
    }

    public function testRefreshTokenWithDeactivatedUserReturnsFalse(): void
    {
        $this->connection->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        $refreshToken = $this->jwtAuth->generateRefreshToken(['user_id' => 999]);
        $result = $this->jwtAuth->refreshToken($refreshToken);
        $this->assertFalse($result);
    }

    public function testRevokeTokenReturnsTrue(): void
    {
        $token = $this->jwtAuth->generateToken(['user_id' => 1]);
        $result = $this->jwtAuth->revokeToken($token);
        $this->assertTrue($result);
    }

    public function testValidateReturnsTrueForValidToken(): void
    {
        $token = $this->jwtAuth->generateToken(['user_id' => 1]);
        $this->assertTrue($this->jwtAuth->validate($token));
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse($this->jwtAuth->validate('invalid'));
    }

    public function testGenerateTokenIncludesCustomPayload(): void
    {
        $payload = [
            'user_id' => 42,
            'email' => 'custom@test.com',
            'role' => 'developer',
            'custom_field' => 'custom_value',
        ];

        $token = $this->jwtAuth->generateToken($payload);
        $decoded = $this->jwtAuth->validateToken($token);

        $this->assertIsArray($decoded);
        $this->assertEquals(42, $decoded['user_id']);
        $this->assertEquals('custom@test.com', $decoded['email']);
        $this->assertEquals('developer', $decoded['role']);
        $this->assertEquals('custom_value', $decoded['custom_field']);
    }
}
