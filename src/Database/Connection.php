<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Database;

use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO connection manager implementing singleton pattern.
 */
class Connection
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var PDO The PDO connection */
    private PDO $pdo;

    /**
     * Private constructor to enforce singleton pattern.
     *
     * @param Config $config Configuration object
     * @throws DatabaseException If connection fails
     */
    private function __construct(Config $config)
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config->getString('DB_HOST', 'localhost'),
                $config->getString('DB_PORT', '3306'),
                $config->getString('DB_NAME', ''),
                $config->getString('DB_CHARSET', 'utf8mb4')
            );

            $this->pdo = new PDO(
                $dsn,
                $config->getString('DB_USER', 'root'),
                $config->getString('DB_PASSWORD', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
                ]
            );
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Database connection failed: {$e->getMessage()}",
                500,
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get singleton instance.
     *
     * @param Config|null $config Configuration (required on first call)
     * @return self
     * @throws DatabaseException If config is missing on first call
     */
    public static function getInstance(?Config $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new DatabaseException('Config is required to create database connection');
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Reset singleton instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a raw SQL query.
     *
     * @param string $sql SQL query
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function query(string $sql): PDOStatement
    {
        try {
            $result = $this->pdo->query($sql);
            if ($result === false) {
                throw new DatabaseException("Query failed: {$sql}");
            }
            return $result;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Prepare a SQL statement.
     *
     * @param string $sql SQL query with placeholders
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function prepare(string $sql): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new DatabaseException("Failed to prepare statement: {$sql}");
            }
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException("Prepare failed: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Execute a prepared statement with parameters.
     *
     * @param string $sql SQL query with placeholders
     * @param array<mixed> $params Bound parameters
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException("Execute failed: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Fetch all rows from a query.
     *
     * @param string $sql SQL query
     * @param array<mixed> $params Bound parameters
     * @return array<array<string, mixed>>
     * @throws DatabaseException
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single row from a query.
     *
     * @param string $sql SQL query
     * @param array<mixed> $params Bound parameters
     * @return array<string, mixed>|null
     * @throws DatabaseException
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch a single column value.
     *
     * @param string $sql SQL query
     * @param array<mixed> $params Bound parameters
     * @return mixed
     * @throws DatabaseException
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Get the last inserted ID.
     */
    public function lastInsertId(): string
    {
        $id = $this->pdo->lastInsertId();
        return $id !== false ? $id : '0';
    }

    /**
     * Begin a database transaction.
     *
     * @throws DatabaseException
     */
    public function beginTransaction(): bool
    {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to begin transaction: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Commit the current transaction.
     *
     * @throws DatabaseException
     */
    public function commit(): bool
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to commit transaction: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Rollback the current transaction.
     *
     * @throws DatabaseException
     */
    public function rollback(): bool
    {
        try {
            return $this->pdo->rollBack();
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to rollback transaction: {$e->getMessage()}", 500, 0, $e);
        }
    }

    /**
     * Check if currently in a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
