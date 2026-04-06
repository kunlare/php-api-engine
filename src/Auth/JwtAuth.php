<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Kunlare\PhpCrudApi\Api\Request;
use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;
use Kunlare\PhpCrudApi\Exceptions\AuthException;
use Throwable;

/**
 * JWT (JSON Web Token) authentication strategy.
 */
class JwtAuth implements AuthInterface
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Config Configuration */
    private Config $config;

    /** @var string JWT secret key */
    private string $secret;

    /** @var string JWT algorithm */
    private string $algorithm;

    /** @var int Token expiration in seconds */
    private int $expiration;

    /** @var int Refresh token expiration in seconds */
    private int $refreshExpiration;

    /**
     * @param Connection $connection Database connection
     * @param Config $config Configuration
     */
    public function __construct(Connection $connection, Config $config)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->secret = $config->getString('JWT_SECRET', 'change-this-to-a-random-secret-key-min-32-chars');
        $this->algorithm = $config->getString('JWT_ALGORITHM', 'HS256');
        $this->expiration = $config->getInt('JWT_EXPIRATION', 3600);
        $this->refreshExpiration = $config->getInt('JWT_REFRESH_EXPIRATION', 604800);
    }

    /**
     * Authenticate a request using JWT Bearer token.
     *
     * @param Request $request The HTTP request
     * @return array<string, mixed>|null User data or null
     */
    public function authenticate(Request $request): ?array
    {
        $token = $request->getBearerToken();
        if ($token === null) {
            return null;
        }

        $payload = $this->validateToken($token);
        if ($payload === false) {
            return null;
        }

        return $payload;
    }

    /**
     * Validate a JWT token string.
     *
     * @param string $credentials The JWT token
     * @return bool True if valid
     */
    public function validate(string $credentials): bool
    {
        return $this->validateToken($credentials) !== false;
    }

    /**
     * Generate a JWT access token.
     *
     * @param array<string, mixed> $payload Token payload data
     * @return string Encoded JWT token
     */
    public function generateToken(array $payload): string
    {
        $now = time();
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $this->expiration,
            'type' => 'access',
        ]);

        return JWT::encode($tokenPayload, $this->secret, $this->algorithm);
    }

    /**
     * Generate a JWT refresh token.
     *
     * @param array<string, mixed> $payload Token payload data
     * @return string Encoded JWT refresh token
     */
    public function generateRefreshToken(array $payload): string
    {
        $now = time();
        $tokenPayload = [
            'user_id' => $payload['user_id'] ?? null,
            'iat' => $now,
            'exp' => $now + $this->refreshExpiration,
            'type' => 'refresh',
        ];

        return JWT::encode($tokenPayload, $this->secret, $this->algorithm);
    }

    /**
     * Validate and decode a JWT token.
     *
     * @param string $token JWT token string
     * @return array<string, mixed>|false Decoded payload or false
     */
    public function validateToken(string $token): array|false
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @param string $refreshToken The refresh token
     * @return string|false New access token or false
     * @throws AuthException
     */
    public function refreshToken(string $refreshToken): string|false
    {
        $payload = $this->validateToken($refreshToken);
        if ($payload === false) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            return false;
        }

        $userId = $payload['user_id'] ?? null;
        if ($userId === null) {
            return false;
        }

        $usersTable = $this->config->getString('USERS_TABLE', 'users');
        $user = $this->connection->fetch(
            "SELECT id, username, email, role FROM `{$usersTable}` WHERE id = ? AND is_active = 1",
            [$userId]
        );

        if ($user === null) {
            return false;
        }

        return $this->generateToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
    }

    /**
     * Revoke a token (placeholder for token blacklist implementation).
     *
     * @param string $token Token to revoke
     * @return bool True on success
     */
    public function revokeToken(string $token): bool
    {
        // In a production system, you would add the token to a blacklist
        // For now, this is a no-op since JWTs are stateless
        return true;
    }
}
