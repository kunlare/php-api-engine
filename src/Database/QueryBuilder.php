<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Database;

use Kunlare\PhpApiEngine\Exceptions\DatabaseException;

/**
 * Fluent query builder for constructing and executing SQL queries.
 */
class QueryBuilder
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var string Table name */
    private string $table = '';

    /** @var array<string> Columns to select */
    private array $columns = ['*'];

    /** @var array<array{sql: string, params: array<mixed>}> WHERE conditions */
    private array $wheres = [];

    /** @var array<array{column: string, direction: string}> ORDER BY clauses */
    private array $orders = [];

    /** @var int|null LIMIT value */
    private ?int $limitValue = null;

    /** @var int|null OFFSET value */
    private ?int $offsetValue = null;

    /**
     * @param Connection $connection Database connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set the table and columns for a SELECT query.
     *
     * @param string $table Table name
     * @param array<string> $columns Columns to select
     * @return self
     */
    public function select(string $table, array $columns = ['*']): self
    {
        $clone = clone $this;
        $clone->reset();
        $clone->table = $table;
        $clone->columns = $columns;
        return $clone;
    }

    /**
     * Add a WHERE condition.
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = [
            'sql' => sprintf('`%s` %s ?', str_replace('`', '``', $column), $this->validateOperator($operator)),
            'params' => [$value],
        ];
        return $clone;
    }

    /**
     * Add a WHERE IN condition.
     *
     * @param string $column Column name
     * @param array<mixed> $values Values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // Empty IN clause should return no results
            $clone = clone $this;
            $clone->wheres[] = ['sql' => '1 = 0', 'params' => []];
            return $clone;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $clone = clone $this;
        $clone->wheres[] = [
            'sql' => sprintf('`%s` IN (%s)', str_replace('`', '``', $column), $placeholders),
            'params' => array_values($values),
        ];
        return $clone;
    }

    /**
     * Add a WHERE LIKE condition.
     *
     * @param string $column Column name
     * @param string $value LIKE pattern
     * @return self
     */
    public function whereLike(string $column, string $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = [
            'sql' => sprintf('`%s` LIKE ?', str_replace('`', '``', $column)),
            'params' => [$value],
        ];
        return $clone;
    }

    /**
     * Add a WHERE IS NULL condition.
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self
    {
        $clone = clone $this;
        $clone->wheres[] = [
            'sql' => sprintf('`%s` IS NULL', str_replace('`', '``', $column)),
            'params' => [],
        ];
        return $clone;
    }

    /**
     * Add a WHERE IS NOT NULL condition.
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $clone = clone $this;
        $clone->wheres[] = [
            'sql' => sprintf('`%s` IS NOT NULL', str_replace('`', '``', $column)),
            'params' => [],
        ];
        return $clone;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column Column name
     * @param string $direction Sort direction (ASC or DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $clone = clone $this;
        $clone->orders[] = ['column' => $column, 'direction' => $direction];
        return $clone;
    }

    /**
     * Set the LIMIT value.
     *
     * @param int $limit Maximum number of rows
     * @return self
     */
    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limitValue = $limit;
        return $clone;
    }

    /**
     * Set the OFFSET value.
     *
     * @param int $offset Number of rows to skip
     * @return self
     */
    public function offset(int $offset): self
    {
        $clone = clone $this;
        $clone->offsetValue = $offset;
        return $clone;
    }

    /**
     * Execute the SELECT query and return all rows.
     *
     * @return array<array<string, mixed>>
     * @throws DatabaseException
     */
    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();
        return $this->connection->fetchAll($sql, $params);
    }

    /**
     * Execute the SELECT query and return the first row.
     *
     * @return array<string, mixed>|null
     * @throws DatabaseException
     */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limitValue = 1;
        [$sql, $params] = $clone->buildSelect();
        return $this->connection->fetch($sql, $params);
    }

    /**
     * Count the number of matching rows.
     *
     * @return int
     * @throws DatabaseException
     */
    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) as count'];
        [$sql, $params] = $clone->buildSelect();
        $result = $this->connection->fetch($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Column-value pairs
     * @return int|bool Last insert ID or true
     * @throws DatabaseException
     */
    public function insert(string $table, array $data): int|bool
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert empty data');
        }

        $columns = array_keys($data);
        $quotedColumns = array_map(fn(string $c) => '`' . str_replace('`', '``', $c) . '`', $columns);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '``', $table),
            implode(', ', $quotedColumns),
            $placeholders
        );

        $this->connection->execute($sql, array_values($data));
        $id = $this->connection->lastInsertId();
        return $id !== '0' ? (int) $id : true;
    }

    /**
     * Update rows in a table. Uses current WHERE conditions.
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Column-value pairs to update
     * @return bool True on success
     * @throws DatabaseException
     */
    public function update(string $table, array $data): bool
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot update with empty data');
        }

        $setClauses = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setClauses[] = sprintf('`%s` = ?', str_replace('`', '``', $column));
            $params[] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s',
            str_replace('`', '``', $table),
            implode(', ', $setClauses)
        );

        [$whereSql, $whereParams] = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            $params = array_merge($params, $whereParams);
        }

        $this->connection->execute($sql, $params);
        return true;
    }

    /**
     * Delete rows from a table. Uses current WHERE conditions.
     *
     * @param string $table Table name
     * @return bool True on success
     * @throws DatabaseException
     */
    public function delete(string $table): bool
    {
        $sql = sprintf('DELETE FROM `%s`', str_replace('`', '``', $table));

        [$whereSql, $whereParams] = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $this->connection->execute($sql, $whereParams);
        return true;
    }

    /**
     * Apply filters from an associative array.
     *
     * @param string $table Table name
     * @param array<string, array{operator: string, value: mixed}|mixed> $filters Filter definitions
     * @return self
     */
    public function applyFilters(string $table, array $filters): self
    {
        $clone = $this->select($table);

        foreach ($filters as $column => $filter) {
            if (is_array($filter) && isset($filter['operator'])) {
                $operator = strtoupper($filter['operator']);
                $value = $filter['value'];

                if ($operator === 'LIKE') {
                    $clone = $clone->whereLike($column, (string) $value);
                } elseif ($operator === 'IN') {
                    $clone = $clone->whereIn($column, (array) $value);
                } elseif ($operator === 'IS NULL') {
                    $clone = $clone->whereNull($column);
                } elseif ($operator === 'IS NOT NULL') {
                    $clone = $clone->whereNotNull($column);
                } else {
                    $clone = $clone->where($column, $operator, $value);
                }
            } else {
                // Simple equality filter
                $clone = $clone->where($column, '=', $filter);
            }
        }

        return $clone;
    }

    /**
     * Build the full SELECT SQL statement.
     *
     * @return array{0: string, 1: array<mixed>}
     */
    private function buildSelect(): array
    {
        $columns = implode(', ', $this->columns);
        $sql = sprintf('SELECT %s FROM `%s`', $columns, str_replace('`', '``', $this->table));
        $params = [];

        [$whereSql, $whereParams] = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            $params = array_merge($params, $whereParams);
        }

        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = sprintf('`%s` %s', str_replace('`', '``', $order['column']), $order['direction']);
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return [$sql, $params];
    }

    /**
     * Build the WHERE clause from conditions.
     *
     * @return array{0: string, 1: array<mixed>}
     */
    private function buildWhereClause(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }

        $parts = [];
        $params = [];

        foreach ($this->wheres as $where) {
            $parts[] = $where['sql'];
            $params = array_merge($params, $where['params']);
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * Validate a comparison operator.
     *
     * @param string $operator Operator to validate
     * @return string Validated operator
     * @throws DatabaseException
     */
    private function validateOperator(string $operator): string
    {
        $allowed = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'];
        $upper = strtoupper(trim($operator));

        if (!in_array($upper, $allowed, true)) {
            throw new DatabaseException("Invalid operator: {$operator}");
        }

        return $upper;
    }

    /**
     * Reset the builder state.
     */
    private function reset(): void
    {
        $this->table = '';
        $this->columns = ['*'];
        $this->wheres = [];
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;
    }
}
