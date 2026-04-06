<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Database;

use Kunlare\PhpCrudApi\Exceptions\DatabaseException;

/**
 * Schema builder for creating/altering/dropping MySQL tables.
 */
class SchemaBuilder
{
    /** @var Connection Database connection */
    private Connection $connection;

    /**
     * @param Connection $connection Database connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new table.
     *
     * @param string $tableName Table name
     * @param array<string, array<string, mixed>> $columns Column definitions
     * @param array<string, mixed> $options Table options
     * @return bool True on success
     * @throws DatabaseException
     *
     * Example:
     *   $schema->createTable('products', [
     *       'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
     *       'name' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => false],
     *       'price' => ['type' => 'DECIMAL', 'precision' => 10, 'scale' => 2],
     *       'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
     *   ]);
     */
    public function createTable(string $tableName, array $columns, array $options = []): bool
    {
        $this->validateTableName($tableName);

        $columnDefs = [];
        $primaryKeys = [];
        $indexes = [];
        $uniqueKeys = [];
        $foreignKeys = [];

        foreach ($columns as $name => $definition) {
            $columnDefs[] = $this->buildColumnDefinition($name, $definition);

            if (!empty($definition['primary'])) {
                $primaryKeys[] = $this->quoteIdentifier($name);
            }
            if (!empty($definition['index'])) {
                $indexes[] = $name;
            }
            if (!empty($definition['unique'])) {
                $uniqueKeys[] = $name;
            }
            if (!empty($definition['foreign_key'])) {
                $foreignKeys[$name] = $definition['foreign_key'];
            }
        }

        $parts = $columnDefs;

        if (!empty($primaryKeys)) {
            $parts[] = 'PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
        }

        foreach ($uniqueKeys as $col) {
            $parts[] = sprintf('UNIQUE KEY `uk_%s_%s` (%s)', $tableName, $col, $this->quoteIdentifier($col));
        }

        foreach ($indexes as $col) {
            $parts[] = sprintf('INDEX `idx_%s_%s` (%s)', $tableName, $col, $this->quoteIdentifier($col));
        }

        foreach ($foreignKeys as $col => $fk) {
            $onDelete = $fk['on_delete'] ?? 'CASCADE';
            $onUpdate = $fk['on_update'] ?? 'CASCADE';
            $parts[] = sprintf(
                'FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE %s',
                $this->quoteIdentifier($col),
                $this->quoteIdentifier($fk['table']),
                $this->quoteIdentifier($fk['column']),
                $onDelete,
                $onUpdate
            );
        }

        $engine = $options['engine'] ?? 'InnoDB';
        $charset = $options['charset'] ?? 'utf8mb4';
        $collate = $options['collate'] ?? 'utf8mb4_unicode_ci';
        $ifNotExists = ($options['if_not_exists'] ?? true) ? 'IF NOT EXISTS ' : '';

        $sql = sprintf(
            "CREATE TABLE %s%s (\n  %s\n) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s",
            $ifNotExists,
            $this->quoteIdentifier($tableName),
            implode(",\n  ", $parts),
            $engine,
            $charset,
            $collate
        );

        $this->connection->query($sql);
        return true;
    }

    /**
     * Alter an existing table.
     *
     * @param string $tableName Table name
     * @param array<array<string, mixed>> $modifications List of modifications
     * @return bool True on success
     * @throws DatabaseException
     */
    public function alterTable(string $tableName, array $modifications): bool
    {
        $this->validateTableName($tableName);

        $parts = [];
        foreach ($modifications as $mod) {
            $action = strtoupper($mod['action'] ?? '');
            switch ($action) {
                case 'ADD':
                    $parts[] = 'ADD COLUMN ' . $this->buildColumnDefinition($mod['column'], $mod['definition']);
                    break;
                case 'MODIFY':
                    $parts[] = 'MODIFY COLUMN ' . $this->buildColumnDefinition($mod['column'], $mod['definition']);
                    break;
                case 'DROP':
                    $parts[] = 'DROP COLUMN ' . $this->quoteIdentifier($mod['column']);
                    break;
                case 'RENAME':
                    $parts[] = sprintf(
                        'RENAME COLUMN %s TO %s',
                        $this->quoteIdentifier($mod['column']),
                        $this->quoteIdentifier($mod['new_name'])
                    );
                    break;
                default:
                    throw new DatabaseException("Unknown alter action: {$action}");
            }
        }

        $sql = sprintf(
            'ALTER TABLE %s %s',
            $this->quoteIdentifier($tableName),
            implode(', ', $parts)
        );

        $this->connection->query($sql);
        return true;
    }

    /**
     * Drop a table.
     *
     * @param string $tableName Table name
     * @param bool $ifExists Add IF EXISTS clause
     * @return bool True on success
     * @throws DatabaseException
     */
    public function dropTable(string $tableName, bool $ifExists = true): bool
    {
        $this->validateTableName($tableName);
        $ifExistsClause = $ifExists ? 'IF EXISTS ' : '';
        $sql = sprintf('DROP TABLE %s%s', $ifExistsClause, $this->quoteIdentifier($tableName));
        $this->connection->query($sql);
        return true;
    }

    /**
     * Check if a table exists.
     *
     * @param string $tableName Table name
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->connection->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tableName]
        );
        return (int) $result > 0;
    }

    /**
     * Get columns of a table.
     *
     * @param string $tableName Table name
     * @return array<array<string, mixed>>
     * @throws DatabaseException
     */
    public function getTableColumns(string $tableName): array
    {
        return $this->connection->fetchAll(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$tableName]
        );
    }

    /**
     * Add a column to an existing table.
     *
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @param array<string, mixed> $definition Column definition
     * @return bool True on success
     * @throws DatabaseException
     */
    public function addColumn(string $tableName, string $columnName, array $definition): bool
    {
        return $this->alterTable($tableName, [
            ['action' => 'ADD', 'column' => $columnName, 'definition' => $definition],
        ]);
    }

    /**
     * Drop a column from a table.
     *
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool True on success
     * @throws DatabaseException
     */
    public function dropColumn(string $tableName, string $columnName): bool
    {
        return $this->alterTable($tableName, [
            ['action' => 'DROP', 'column' => $columnName],
        ]);
    }

    /**
     * Build SQL column definition from array.
     *
     * @param string $name Column name
     * @param array<string, mixed> $definition Column definition
     * @return string SQL column definition
     */
    private function buildColumnDefinition(string $name, array $definition): string
    {
        $type = strtoupper($definition['type'] ?? 'VARCHAR');
        $sql = $this->quoteIdentifier($name) . ' ' . $this->buildTypeString($type, $definition);

        if (isset($definition['nullable']) && $definition['nullable'] === false) {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $definition)) {
            $default = $definition['default'];
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (in_array(strtoupper((string) $default), ['CURRENT_TIMESTAMP', 'NOW()'], true)) {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= " DEFAULT '" . addslashes((string) $default) . "'";
            }
        }

        if (!empty($definition['auto_increment'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!empty($definition['on_update'])) {
            $onUpdate = strtoupper($definition['on_update']);
            if ($onUpdate === 'CURRENT_TIMESTAMP') {
                $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
            }
        }

        return $sql;
    }

    /**
     * Build SQL type string from column type and definition.
     *
     * @param string $type Column type
     * @param array<string, mixed> $definition Column definition
     * @return string SQL type string
     */
    private function buildTypeString(string $type, array $definition): string
    {
        return match ($type) {
            'VARCHAR', 'CHAR' => $type . '(' . ($definition['length'] ?? 255) . ')',
            'DECIMAL' => sprintf('DECIMAL(%d,%d)', $definition['precision'] ?? 10, $definition['scale'] ?? 2),
            'ENUM' => "ENUM('" . implode("','", $definition['values'] ?? []) . "')",
            'SET' => "SET('" . implode("','", $definition['values'] ?? []) . "')",
            'TINYINT' => isset($definition['boolean']) && $definition['boolean']
                ? 'TINYINT(1)'
                : 'TINYINT',
            'BOOLEAN' => 'TINYINT(1)',
            default => $type,
        };
    }

    /**
     * Validate table name to prevent SQL injection.
     *
     * @param string $name Table name
     * @throws DatabaseException
     */
    private function validateTableName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new DatabaseException("Invalid table name: {$name}");
        }
    }

    /**
     * Quote an identifier (table/column name) with backticks.
     *
     * @param string $identifier Identifier to quote
     * @return string Quoted identifier
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
