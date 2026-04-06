<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Api\Response;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Database\SchemaBuilder;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;

/**
 * Controller for schema management via API.
 *
 * Read operations (list tables, get table structure) are accessible to all
 * authenticated users. Write operations (create, alter, drop) require admin.
 *
 * Endpoints:
 *   GET    /api/v1/schema/tables              → list all tables
 *   POST   /api/v1/schema/tables              → create a new table
 *   GET    /api/v1/schema/tables/{table}       → get table columns
 *   DELETE /api/v1/schema/tables/{table}       → drop a table
 *   POST   /api/v1/schema/tables/{table}/columns       → add a column
 *   PATCH  /api/v1/schema/tables/{table}/columns/{col} → modify a column
 *   DELETE /api/v1/schema/tables/{table}/columns/{col} → drop a column
 */
class SchemaController
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var SchemaBuilder Schema builder */
    private SchemaBuilder $schemaBuilder;

    /** @var Request HTTP request */
    private Request $request;

    /** @var Config Configuration */
    private Config $config;

    /**
     * @param Connection $connection Database connection
     * @param Request $request HTTP request
     * @param Config $config Configuration
     */
    public function __construct(Connection $connection, Request $request, Config $config)
    {
        $this->connection = $connection;
        $this->schemaBuilder = new SchemaBuilder($connection);
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * List all tables in the current database.
     *
     * @throws AuthException
     */
    public function listTables(): void
    {
        $this->requireAuth();

        $tables = $this->connection->fetchAll(
            "SELECT TABLE_NAME, TABLE_ROWS, ENGINE, TABLE_COLLATION, CREATE_TIME, UPDATE_TIME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME"
        );

        Response::success($tables);
    }

    /**
     * Create a new table.
     *
     * Request body example:
     * {
     *   "table": "products",
     *   "columns": {
     *     "id":         {"type": "INT", "auto_increment": true, "primary": true},
     *     "name":       {"type": "VARCHAR", "length": 255, "nullable": false},
     *     "price":      {"type": "DECIMAL", "precision": 10, "scale": 2},
     *     "category":   {"type": "VARCHAR", "length": 100, "index": true},
     *     "created_at": {"type": "TIMESTAMP", "default": "CURRENT_TIMESTAMP"}
     *   },
     *   "options": {
     *     "engine": "InnoDB",
     *     "charset": "utf8mb4"
     *   }
     * }
     *
     * @throws AuthException
     * @throws ValidationException
     */
    public function createTable(): void
    {
        $this->requireAdmin();

        $data = $this->request->getJson();

        if (empty($data['table'])) {
            throw new ValidationException('The "table" field is required');
        }
        if (empty($data['columns']) || !is_array($data['columns'])) {
            throw new ValidationException('The "columns" field is required and must be an object');
        }

        $tableName = $data['table'];
        $columns = $data['columns'];
        $options = $data['options'] ?? [];

        $this->schemaBuilder->createTable($tableName, $columns, $options);

        $tableColumns = $this->schemaBuilder->getTableColumns($tableName);

        Response::created([
            'table' => $tableName,
            'columns' => $tableColumns,
            'message' => "Table '{$tableName}' created successfully",
        ]);
    }

    /**
     * Get columns/structure of a table.
     *
     * @param string $table Table name
     * @throws AuthException
     * @throws NotFoundException
     */
    public function getTable(string $table): void
    {
        $this->requireAuth();

        if (!$this->schemaBuilder->tableExists($table)) {
            throw new NotFoundException("Table '{$table}' not found");
        }

        $columns = $this->schemaBuilder->getTableColumns($table);

        Response::success([
            'table' => $table,
            'columns' => $columns,
        ]);
    }

    /**
     * Drop a table.
     *
     * @param string $table Table name
     * @throws AuthException
     * @throws ValidationException
     */
    public function dropTable(string $table): void
    {
        $this->requireAdmin();

        // Protect system tables from accidental deletion
        $protectedTables = [
            $this->config->getString('USERS_TABLE', 'users'),
            $this->config->getString('API_KEYS_TABLE', 'api_keys'),
        ];

        if (in_array($table, $protectedTables, true)) {
            throw new ValidationException("Cannot drop system table '{$table}'");
        }

        if (!$this->schemaBuilder->tableExists($table)) {
            throw new NotFoundException("Table '{$table}' not found");
        }

        $this->schemaBuilder->dropTable($table);

        Response::success([
            'message' => "Table '{$table}' dropped successfully",
        ]);
    }

    /**
     * Add a column to a table.
     *
     * Request body example:
     * {
     *   "column": "description",
     *   "definition": {
     *     "type": "TEXT",
     *     "nullable": true
     *   }
     * }
     *
     * @param string $table Table name
     * @throws AuthException
     * @throws ValidationException
     * @throws NotFoundException
     */
    public function addColumn(string $table): void
    {
        $this->requireAdmin();

        if (!$this->schemaBuilder->tableExists($table)) {
            throw new NotFoundException("Table '{$table}' not found");
        }

        $data = $this->request->getJson();

        if (empty($data['column'])) {
            throw new ValidationException('The "column" field is required');
        }
        if (empty($data['definition']) || !is_array($data['definition'])) {
            throw new ValidationException('The "definition" field is required and must be an object');
        }

        $this->schemaBuilder->addColumn($table, $data['column'], $data['definition']);

        $columns = $this->schemaBuilder->getTableColumns($table);

        Response::created([
            'table' => $table,
            'column' => $data['column'],
            'columns' => $columns,
            'message' => "Column '{$data['column']}' added to '{$table}'",
        ]);
    }

    /**
     * Modify a column in a table.
     *
     * Request body example:
     * {
     *   "definition": {
     *     "type": "VARCHAR",
     *     "length": 500,
     *     "nullable": true
     *   }
     * }
     *
     * @param string $table Table name
     * @param string $column Column name
     * @throws AuthException
     * @throws ValidationException
     * @throws NotFoundException
     */
    public function modifyColumn(string $table, string $column): void
    {
        $this->requireAdmin();

        if (!$this->schemaBuilder->tableExists($table)) {
            throw new NotFoundException("Table '{$table}' not found");
        }

        $data = $this->request->getJson();

        if (empty($data['definition']) || !is_array($data['definition'])) {
            throw new ValidationException('The "definition" field is required and must be an object');
        }

        $this->schemaBuilder->alterTable($table, [
            ['action' => 'MODIFY', 'column' => $column, 'definition' => $data['definition']],
        ]);

        $columns = $this->schemaBuilder->getTableColumns($table);

        Response::success([
            'table' => $table,
            'column' => $column,
            'columns' => $columns,
            'message' => "Column '{$column}' in '{$table}' modified successfully",
        ]);
    }

    /**
     * Drop a column from a table.
     *
     * @param string $table Table name
     * @param string $column Column name
     * @throws AuthException
     * @throws NotFoundException
     */
    public function dropColumn(string $table, string $column): void
    {
        $this->requireAdmin();

        if (!$this->schemaBuilder->tableExists($table)) {
            throw new NotFoundException("Table '{$table}' not found");
        }

        $this->schemaBuilder->dropColumn($table, $column);

        $columns = $this->schemaBuilder->getTableColumns($table);

        Response::success([
            'table' => $table,
            'columns' => $columns,
            'message' => "Column '{$column}' dropped from '{$table}'",
        ]);
    }

    /**
     * Check that the current user is authenticated.
     *
     * @throws AuthException
     */
    private function requireAuth(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }
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
