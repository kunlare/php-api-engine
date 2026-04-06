<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Api\Response;
use Kunlare\PhpApiEngine\Auth\AuthManager;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;

/**
 * Controller for authentication endpoints (login, register, API key management).
 */
class AuthController
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Request HTTP request */
    private Request $request;

    /** @var Config Configuration */
    private Config $config;

    /** @var AuthManager Auth manager */
    private AuthManager $authManager;

    /** @var Validator Validator */
    private Validator $validator;

    /** @var string Users table name */
    private string $usersTable;

    /**
     * @param Connection $connection Database connection
     * @param Request $request HTTP request
     * @param Config $config Configuration
     * @param AuthManager $authManager Auth manager
     */
    public function __construct(
        Connection $connection,
        Request $request,
        Config $config,
        AuthManager $authManager
    ) {
        $this->connection = $connection;
        $this->request = $request;
        $this->config = $config;
        $this->authManager = $authManager;
        $this->validator = new Validator($connection);
        $this->usersTable = $config->getString('USERS_TABLE', 'users');
    }

    /**
     * Handle user login.
     *
     * @throws ValidationException
     * @throws AuthException
     */
    public function login(): void
    {
        $data = $this->request->getJson();

        $this->validator->validate($data, [
            'email' => 'required',
            'password' => 'required',
        ]);

        $user = $this->connection->fetch(
            "SELECT * FROM `{$this->usersTable}` WHERE (email = ? OR username = ?) AND is_active = 1",
            [$data['email'], $data['email']]
        );

        if ($user === null || !password_verify($data['password'], $user['password_hash'])) {
            throw new AuthException('Invalid credentials', 401);
        }

        $jwt = $this->authManager->getJwtAuth();
        $tokenPayload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        $token = $jwt->generateToken($tokenPayload);
        $refreshToken = $jwt->generateRefreshToken($tokenPayload);

        Response::success([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->getInt('JWT_EXPIRATION', 3600),
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    /**
     * Handle user registration.
     *
     * @throws ValidationException
     */
    public function register(): void
    {
        $data = $this->request->getJson();

        // Check if this is the first user
        $userCount = (int) $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->usersTable}`"
        );

        $isFirstUser = $userCount === 0;

        // If not first user, require admin authentication
        if (!$isFirstUser) {
            $user = $this->request->getUser();
            if ($user === null || ($user['role'] ?? '') !== 'admin') {
                throw new AuthException('Only admins can register new users', 403);
            }
        }

        $this->validator->validate($data, [
            'username' => "required|string|min:3|max:50|unique:{$this->usersTable},username",
            'email' => "required|email|unique:{$this->usersTable},email",
            'password' => 'required|string|min:8',
        ]);

        $minLength = $this->config->getInt('MIN_PASSWORD_LENGTH', 8);
        $requireSpecial = $this->config->getBool('REQUIRE_SPECIAL_CHARS', true);

        if (!$this->validator->validatePassword($data['password'], $minLength, $requireSpecial)) {
            throw new ValidationException('Password does not meet requirements', [
                'password' => ["Password must be at least {$minLength} characters" .
                    ($requireSpecial ? ' and contain special characters' : '')],
            ]);
        }

        // First user becomes admin if configured
        $role = $isFirstUser && $this->config->getBool('FIRST_USER_IS_ADMIN', true)
            ? 'admin'
            : ($data['role'] ?? 'user');

        if (!$isFirstUser && isset($data['role']) && $data['role'] !== 'user') {
            $currentUser = $this->request->getUser();
            if ($currentUser === null || ($currentUser['role'] ?? '') !== 'admin') {
                $role = 'user';
            }
        }

        $this->connection->execute(
            "INSERT INTO `{$this->usersTable}` (username, email, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, 1)",
            [
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $role,
            ]
        );

        $userId = (int) $this->connection->lastInsertId();

        // Generate token for the new user
        $jwt = $this->authManager->getJwtAuth();
        $token = $jwt->generateToken([
            'user_id' => $userId,
            'email' => $data['email'],
            'role' => $role,
        ]);

        Response::created([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $userId,
                'username' => $data['username'],
                'email' => $data['email'],
                'role' => $role,
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile.
     *
     * @throws AuthException
     */
    public function getProfile(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['id']);
        $profile = $this->connection->fetch(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` WHERE id = ?",
            [$userId]
        );

        if ($profile === null) {
            throw new AuthException('User not found', 404);
        }

        Response::success($profile);
    }

    /**
     * Update the authenticated user's own profile.
     *
     * @throws AuthException
     * @throws ValidationException
     */
    public function updateProfile(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['id']);
        $data = $this->request->getJson();

        if (empty($data)) {
            throw new ValidationException('Request body cannot be empty');
        }

        $setClauses = [];
        $params = [];

        if (isset($data['username'])) {
            $setClauses[] = 'username = ?';
            $params[] = $data['username'];
        }

        if (isset($data['email'])) {
            if (!$this->validator->validateEmail($data['email'])) {
                throw new ValidationException('Invalid email format');
            }
            $setClauses[] = 'email = ?';
            $params[] = $data['email'];
        }

        if (isset($data['new_password'])) {
            // Require current password for password changes
            if (!isset($data['current_password']) || $data['current_password'] === '') {
                throw new ValidationException('Current password is required to set a new password');
            }

            $existing = $this->connection->fetch(
                "SELECT password_hash FROM `{$this->usersTable}` WHERE id = ?",
                [$userId]
            );

            if ($existing === null || !password_verify($data['current_password'], $existing['password_hash'])) {
                throw new AuthException('Current password is incorrect', 403);
            }

            $minLength = $this->config->getInt('MIN_PASSWORD_LENGTH', 8);
            $requireSpecial = $this->config->getBool('REQUIRE_SPECIAL_CHARS', true);

            if (!$this->validator->validatePassword($data['new_password'], $minLength, $requireSpecial)) {
                throw new ValidationException('Password does not meet requirements', [
                    'new_password' => ["Password must be at least {$minLength} characters" .
                        ($requireSpecial ? ' and contain special characters' : '')],
                ]);
            }

            $setClauses[] = 'password_hash = ?';
            $params[] = password_hash($data['new_password'], PASSWORD_BCRYPT);
        }

        if (empty($setClauses)) {
            throw new ValidationException('No valid fields to update');
        }

        $params[] = $userId;
        $sql = sprintf(
            "UPDATE `{$this->usersTable}` SET %s WHERE id = ?",
            implode(', ', $setClauses)
        );

        $this->connection->execute($sql, $params);

        $profile = $this->connection->fetch(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` WHERE id = ?",
            [$userId]
        );

        Response::success($profile);
    }

    /**
     * Refresh an access token.
     *
     * @throws AuthException
     */
    public function refresh(): void
    {
        $data = $this->request->getJson();
        $refreshToken = $data['refresh_token'] ?? null;

        if ($refreshToken === null || $refreshToken === '') {
            throw new ValidationException('refresh_token is required');
        }

        $jwt = $this->authManager->getJwtAuth();
        $newToken = $jwt->refreshToken($refreshToken);

        if ($newToken === false) {
            throw new AuthException('Invalid or expired refresh token', 401);
        }

        Response::success([
            'token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->getInt('JWT_EXPIRATION', 3600),
        ]);
    }

    /**
     * Create a new API key for the authenticated user.
     *
     * @throws AuthException
     */
    public function createApiKey(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }

        $data = $this->request->getJson();
        $name = $data['name'] ?? 'Default';
        $expiresAt = $data['expires_at'] ?? null;
        $permissions = $data['permissions'] ?? null;

        $userId = (int) ($user['user_id'] ?? $user['id']);
        $apiKeyAuth = $this->authManager->getApiKeyAuth();
        $result = $apiKeyAuth->generateKey($userId, $name, $expiresAt, $permissions);

        Response::created([
            'api_key' => $result['key'],
            'id' => $result['id'],
            'name' => $name,
            'message' => 'Store this key securely. It will not be shown again.',
        ]);
    }

    /**
     * List API keys for the authenticated user.
     *
     * @throws AuthException
     */
    public function listApiKeys(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['id']);
        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');

        $keys = $this->connection->fetchAll(
            "SELECT id, name, permissions, last_used_at, expires_at, is_active, created_at
             FROM `{$apiKeysTable}` WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );

        Response::success($keys);
    }

    /**
     * List all API keys from all users (admin only).
     *
     * @throws AuthException
     */
    public function listAllApiKeys(): void
    {
        $user = $this->request->getUser();
        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            throw new AuthException('Forbidden: Admin access required', 403);
        }

        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');

        $keys = $this->connection->fetchAll(
            "SELECT k.id, k.name, k.user_id, u.username, u.email AS user_email,
                    k.permissions, k.last_used_at, k.expires_at, k.is_active, k.created_at
             FROM `{$apiKeysTable}` k
             LEFT JOIN `{$this->usersTable}` u ON k.user_id = u.id
             ORDER BY k.created_at DESC"
        );

        Response::success($keys);
    }

    /**
     * Revoke (deactivate) an API key.
     * Owners can revoke their own keys. Admins can revoke any key.
     *
     * @param int $id API key ID
     * @throws AuthException
     */
    public function revokeApiKey(int $id): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $userId = (int) ($user['user_id'] ?? $user['id']);
        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');

        if ($isAdmin) {
            $key = $this->connection->fetch(
                "SELECT id FROM `{$apiKeysTable}` WHERE id = ?",
                [$id]
            );
        } else {
            $key = $this->connection->fetch(
                "SELECT id FROM `{$apiKeysTable}` WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        }

        if ($key === null) {
            throw new AuthException('API key not found', 404);
        }

        $this->connection->execute(
            "UPDATE `{$apiKeysTable}` SET is_active = 0 WHERE id = ?",
            [$id]
        );

        Response::success(['message' => 'API key revoked successfully']);
    }
}
