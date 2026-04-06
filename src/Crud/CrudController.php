<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Api\Response;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Database\QueryBuilder;
use Kunlare\PhpApiEngine\Exceptions\DatabaseException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;

/**
 * Generic CRUD controller for any database table.
 */
class CrudController
{
    /** @var Connection Database connection */
    protected Connection $connection;

    /** @var QueryBuilder Query builder */
    protected QueryBuilder $queryBuilder;

    /** @var Request HTTP request */
    protected Request $request;

    /**
     * @param Connection $connection Database connection
     * @param Request $request HTTP request
     */
    public function __construct(Connection $connection, Request $request)
    {
        $this->connection = $connection;
        $this->queryBuilder = new QueryBuilder($connection);
        $this->request = $request;
    }

    /**
     * List all records with pagination.
     *
     * @param string $table Table name
     * @throws DatabaseException
     */
    public function list(string $table): void
    {
        $this->validateTableName($table);

        $page = max(1, (int) ($this->request->getQueryParam('page', 1)));
        $perPage = min(100, max(1, (int) ($this->request->getQueryParam('per_page', 10))));
        $offset = ($page - 1) * $perPage;

        $query = $this->queryBuilder->select($table);

        // Apply ordering
        $orderBy = $this->request->getQueryParam('order_by', 'id');
        $orderDir = strtoupper((string) $this->request->getQueryParam('order_dir', 'ASC'));
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }
        $query = $query->orderBy((string) $orderBy, $orderDir);

        // Count total
        $total = $query->count();

        // Apply pagination
        $data = $query->limit($perPage)->offset($offset)->get();

        Response::success($data, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Get a single record by ID.
     *
     * @param string $table Table name
     * @param mixed $id Record ID
     * @throws NotFoundException
     * @throws DatabaseException
     */
    public function get(string $table, mixed $id): void
    {
        $this->validateTableName($table);

        $record = $this->queryBuilder->select($table)->where('id', '=', $id)->first();

        if ($record === null) {
            throw new NotFoundException("Record not found in {$table} with id {$id}");
        }

        Response::success($record);
    }

    /**
     * Filter records with complex conditions.
     *
     * @param string $table Table name
     * @throws DatabaseException
     */
    public function filter(string $table): void
    {
        $this->validateTableName($table);

        $params = $this->request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        // Build filters from query params
        $filters = [];
        $reserved = ['page', 'per_page', 'order_by', 'order_dir', 'order'];
        foreach ($params as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            // Support operator syntax: field[operator]=value
            if (is_array($value)) {
                foreach ($value as $op => $val) {
                    $operator = $this->mapOperator($op);
                    $filters[$key] = ['operator' => $operator, 'value' => $val];
                }
            } else {
                $filters[$key] = ['operator' => '=', 'value' => $value];
            }
        }

        $query = $this->queryBuilder->applyFilters($table, $filters);

        // Apply ordering
        $order = $this->request->getQueryParam('order');
        if ($order !== null) {
            $orderParts = explode(':', (string) $order);
            $column = $orderParts[0];
            $direction = strtoupper($orderParts[1] ?? 'ASC');
            $query = $query->orderBy($column, $direction);
        } else {
            $orderBy = (string) ($params['order_by'] ?? 'id');
            $orderDir = strtoupper((string) ($params['order_dir'] ?? 'ASC'));
            $query = $query->orderBy($orderBy, $orderDir);
        }

        $total = $query->count();
        $data = $query->limit($perPage)->offset($offset)->get();

        Response::success($data, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Create a new record.
     *
     * @param string $table Table name
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function create(string $table): void
    {
        $this->validateTableName($table);

        $data = $this->request->getJson();
        if (empty($data)) {
            throw new ValidationException('Request body cannot be empty');
        }

        $id = $this->queryBuilder->insert($table, $data);

        $record = null;
        if (is_int($id)) {
            $record = $this->queryBuilder->select($table)->where('id', '=', $id)->first();
        }

        Response::created($record ?? ['id' => $id]);
    }

    /**
     * Update an existing record.
     *
     * @param string $table Table name
     * @param mixed $id Record ID
     * @throws NotFoundException
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function update(string $table, mixed $id): void
    {
        $this->validateTableName($table);

        // Check record exists
        $existing = $this->queryBuilder->select($table)->where('id', '=', $id)->first();
        if ($existing === null) {
            throw new NotFoundException("Record not found in {$table} with id {$id}");
        }

        $data = $this->request->getJson();
        if (empty($data)) {
            throw new ValidationException('Request body cannot be empty');
        }

        $this->queryBuilder->where('id', '=', $id)->update($table, $data);

        $updated = $this->queryBuilder->select($table)->where('id', '=', $id)->first();

        Response::success($updated);
    }

    /**
     * Delete a record.
     *
     * @param string $table Table name
     * @param mixed $id Record ID
     * @throws NotFoundException
     * @throws DatabaseException
     */
    public function delete(string $table, mixed $id): void
    {
        $this->validateTableName($table);

        // Check record exists
        $existing = $this->queryBuilder->select($table)->where('id', '=', $id)->first();
        if ($existing === null) {
            throw new NotFoundException("Record not found in {$table} with id {$id}");
        }

        $this->queryBuilder->where('id', '=', $id)->delete($table);

        Response::noContent();
    }

    /**
     * Map URL operator shorthand to SQL operator.
     *
     * @param string $op Shorthand operator
     * @return string SQL operator
     */
    private function mapOperator(string $op): string
    {
        return match (strtolower($op)) {
            'eq' => '=',
            'neq', 'ne' => '!=',
            'gt' => '>',
            'gte', 'ge' => '>=',
            'lt' => '<',
            'lte', 'le' => '<=',
            'like' => 'LIKE',
            default => '=',
        };
    }

    /**
     * Validate table name for security.
     *
     * @param string $table Table name
     * @throws ValidationException
     */
    protected function validateTableName(string $table): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new ValidationException("Invalid table name: {$table}");
        }
    }
}
