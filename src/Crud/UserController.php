<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Api\Response;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;

/**
 * Controller for user management (admin only).
 */
class UserController
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Request HTTP request */
    private Request $request;

    /** @var Validator Input validator */
    private Validator $validator;

    /** @var string Users table name */
    private string $usersTable;

    /**
     * @param Connection $connection Database connection
     * @param Request $request HTTP request
     * @param Config $config Configuration
     */
    public function __construct(Connection $connection, Request $request, Config $config)
    {
        $this->connection = $connection;
        $this->request = $request;
        $this->validator = new Validator($connection);
        $this->usersTable = $config->getString('USERS_TABLE', 'users');
    }

    /**
     * List all users (admin only).
     *
     * @throws AuthException
     */
    public function list(): void
    {
        $this->requireAdmin();

        $page = max(1, (int) ($this->request->getQueryParam('page', 1)));
        $perPage = min(100, max(1, (int) ($this->request->getQueryParam('per_page', 10))));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->usersTable}`"
        );

        $users = $this->connection->fetchAll(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` ORDER BY id ASC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );

        Response::success($users, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Create a new user (admin only).
     *
     * @throws AuthException
     * @throws ValidationException
     */
    public function create(): void
    {
        $this->requireAdmin();

        $data = $this->request->getJson();

        $this->validator->validate($data, [
            'username' => "required|string|min:3|max:50|unique:{$this->usersTable},username",
            'email' => "required|email|unique:{$this->usersTable},email",
            'password' => 'required|string|min:8',
            'role' => 'in:admin,user,developer',
        ]);

        $role = $data['role'] ?? 'user';
        $isActive = $data['is_active'] ?? true;

        $this->connection->execute(
            "INSERT INTO `{$this->usersTable}` (username, email, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, ?)",
            [
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $role,
                $isActive ? 1 : 0,
            ]
        );

        $id = (int) $this->connection->lastInsertId();
        $user = $this->connection->fetch(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        Response::created($user);
    }

    /**
     * Get a user by ID (admin only).
     *
     * @param int $id User ID
     * @throws AuthException
     * @throws NotFoundException
     */
    public function get(int $id): void
    {
        $this->requireAdmin();

        $user = $this->connection->fetch(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        if ($user === null) {
            throw new NotFoundException("User not found with id {$id}");
        }

        Response::success($user);
    }

    /**
     * Update a user (admin only).
     *
     * @param int $id User ID
     * @throws AuthException
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function update(int $id): void
    {
        $this->requireAdmin();

        $existing = $this->connection->fetch(
            "SELECT * FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        if ($existing === null) {
            throw new NotFoundException("User not found with id {$id}");
        }

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

        if (isset($data['password'])) {
            $setClauses[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (isset($data['role'])) {
            if (!in_array($data['role'], ['admin', 'user', 'developer'], true)) {
                throw new ValidationException('Role must be one of: admin, user, developer');
            }
            $setClauses[] = 'role = ?';
            $params[] = $data['role'];
        }

        if (isset($data['is_active'])) {
            $setClauses[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($setClauses)) {
            throw new ValidationException('No valid fields to update');
        }

        $params[] = $id;
        $sql = sprintf(
            "UPDATE `{$this->usersTable}` SET %s WHERE id = ?",
            implode(', ', $setClauses)
        );

        $this->connection->execute($sql, $params);

        $user = $this->connection->fetch(
            "SELECT id, username, email, role, is_active, created_at, updated_at
             FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        Response::success($user);
    }

    /**
     * Delete a user (admin only).
     *
     * @param int $id User ID
     * @throws AuthException
     * @throws NotFoundException
     */
    public function delete(int $id): void
    {
        $this->requireAdmin();

        $user = $this->request->getUser();

        // Prevent admin from deleting themselves
        if ($user !== null && (int) ($user['id'] ?? $user['user_id'] ?? 0) === $id) {
            throw new ValidationException('Cannot delete your own account');
        }

        $existing = $this->connection->fetch(
            "SELECT id FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        if ($existing === null) {
            throw new NotFoundException("User not found with id {$id}");
        }

        $this->connection->execute(
            "DELETE FROM `{$this->usersTable}` WHERE id = ?",
            [$id]
        );

        Response::noContent();
    }

    /**
     * Check that the current user is an admin.
     *
     * @throws AuthException
     */
    private function requireAdmin(): void
    {
        $user = $this->request->getUser();
        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            throw new AuthException('Forbidden: Admin access required', 403);
        }
    }
}
