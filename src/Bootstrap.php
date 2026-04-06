<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine;

use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\DatabaseException;

/**
 * Bootstraps system tables (users, api_keys, queues, queue_messages) on first run.
 */
class Bootstrap
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
     * Run the bootstrap process: create system tables if they don't exist.
     *
     * @return bool True if tables were created or already exist
     * @throws DatabaseException
     */
    public function run(): bool
    {
        $this->createUsersTable();
        $this->createApiKeysTable();
        $this->createQueuesTable();
        $this->createQueueMessagesTable();
        return true;
    }

    /**
     * Create the users table if it doesn't exist.
     *
     * @throws DatabaseException
     */
    private function createUsersTable(): void
    {
        $table = $this->config->getString('USERS_TABLE', 'users');

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) UNIQUE NOT NULL,
            `email` VARCHAR(100) UNIQUE NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'user', 'developer') DEFAULT 'user',
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->connection->query($sql);
    }

    /**
     * Create the api_keys table if it doesn't exist.
     *
     * @throws DatabaseException
     */
    private function createApiKeysTable(): void
    {
        $apiKeysTable = $this->config->getString('API_KEYS_TABLE', 'api_keys');
        $usersTable = $this->config->getString('USERS_TABLE', 'users');

        $sql = "CREATE TABLE IF NOT EXISTS `{$apiKeysTable}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `key_hash` VARCHAR(255) NOT NULL UNIQUE,
            `name` VARCHAR(100),
            `permissions` JSON,
            `last_used_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `{$usersTable}`(`id`) ON DELETE CASCADE,
            INDEX `idx_key_hash` (`key_hash`),
            INDEX `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->connection->query($sql);
    }

    /**
     * Create the queues table if it doesn't exist.
     *
     * @throws DatabaseException
     */
    private function createQueuesTable(): void
    {
        $table = $this->config->getString('QUEUES_TABLE', 'queues');

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) UNIQUE NOT NULL,
            `direction` ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
            `delivery` ENUM('handler','pull','push') NOT NULL DEFAULT 'handler',
            `delivery_url` VARCHAR(2048) NULL,
            `delivery_headers` JSON NULL,
            `secret` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `max_attempts` INT NOT NULL DEFAULT 3,
            `retry_delay` INT NOT NULL DEFAULT 30,
            `visibility_timeout` INT NOT NULL DEFAULT 60,
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_name` (`name`),
            INDEX `idx_direction` (`direction`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->connection->query($sql);
    }

    /**
     * Create the queue_messages table if it doesn't exist.
     *
     * @throws DatabaseException
     */
    private function createQueueMessagesTable(): void
    {
        $messagesTable = $this->config->getString('QUEUE_MESSAGES_TABLE', 'queue_messages');
        $queuesTable = $this->config->getString('QUEUES_TABLE', 'queues');

        $sql = "CREATE TABLE IF NOT EXISTS `{$messagesTable}` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `queue_id` INT NOT NULL,
            `payload` JSON NOT NULL,
            `status` ENUM('pending','processing','completed','failed','dead') NOT NULL DEFAULT 'pending',
            `priority` INT NOT NULL DEFAULT 0,
            `attempts` INT NOT NULL DEFAULT 0,
            `max_attempts` INT NOT NULL DEFAULT 3,
            `available_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `started_at` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            `error` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`queue_id`) REFERENCES `{$queuesTable}`(`id`) ON DELETE CASCADE,
            INDEX `idx_queue_status` (`queue_id`, `status`, `available_at`),
            INDEX `idx_status` (`status`),
            INDEX `idx_available` (`available_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->connection->query($sql);
    }
}
