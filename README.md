# PHP API ENGINE

![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Version](https://img.shields.io/badge/version-1.0.0-orange)

A framework-agnostic RESTful CRUD API generator for MySQL/MariaDB with multi-mode authentication (Basic Auth, API Key, JWT).

## Features

- **Automatic CRUD endpoints** for any MySQL table
- **Multi-mode authentication**: Basic Auth, API Key, JWT
- **User management** with role-based access (admin, user, developer)
- **Schema builder** for creating/altering tables programmatically
- **Query builder** with fluent API and filter support
- **CORS support** with configurable origins
- **Input validation** with extensible rules
- **CLI setup tool** for initial configuration
- **Standardized JSON responses** with pagination metadata
- **Message queues** with inbound (job), outbound pull, and outbound push (webhook) delivery
- **Secure by default**: prepared statements, password hashing, token-based auth

## Requirements

- PHP >= 8.1
- ext-pdo
- ext-json
- MySQL 5.7+ or MariaDB 10.3+

## Installation

```bash
composer require kunlare/php-crud-api
```

## Quick Start

### 1. Configure Environment

Copy the example configuration and edit it:

```bash
cp vendor/kunlare/php-crud-api/examples/.env.example .env
```

Edit `.env` with your database credentials:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database
DB_USER=root
DB_PASSWORD=your_password
JWT_SECRET=your-random-secret-key-at-least-32-characters
```

### 2. Run Setup

```bash
php vendor/bin/setup.php
```

This will:
- Verify your `.env` configuration
- Test the database connection
- Create `users` and `api_keys` tables
- Prompt you to create an admin account
- Generate a JWT token for first access

### 3. Create Entry Point

Create `index.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Kunlare\PhpCrudApi\Api\Router;
use Kunlare\PhpCrudApi\Bootstrap;
use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;

$config = new Config(__DIR__);
$db = Connection::getInstance($config);

if ($config->getBool('AUTO_SETUP', true)) {
    $bootstrap = new Bootstrap($db, $config);
    $bootstrap->run();
}

$router = new Router($db, $config);
$router->handleRequest();
```

### 4. Start the Server

```bash
php -S localhost:8000 index.php
```

### 5. Open the Admin Panel

Navigate to [http://localhost:8000/admin](http://localhost:8000/admin) to access the graphical admin panel. Log in with your admin credentials to:

- View and create tables
- Add, modify, and drop columns
- Browse, search, create, edit, and delete records
- Manage users and API keys

### 6. Test via CLI

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your_password"}'
```

## Configuration

All settings are managed via `.env` file:

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_NAME` | - | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASSWORD` | - | Database password |
| `DB_CHARSET` | `utf8mb4` | Connection charset |
| `API_VERSION` | `v1` | API version prefix |
| `API_BASE_PATH` | `/api` | API base path |
| `API_DEBUG` | `false` | Show detailed errors |
| `AUTH_METHOD` | `jwt` | Auth mode: `basic`, `apikey`, `jwt` |
| `JWT_SECRET` | - | JWT signing secret |
| `JWT_ALGORITHM` | `HS256` | JWT algorithm |
| `JWT_EXPIRATION` | `3600` | Token lifetime (seconds) |
| `JWT_REFRESH_EXPIRATION` | `604800` | Refresh token lifetime |
| `AUTO_SETUP` | `true` | Auto-create system tables |
| `FIRST_USER_IS_ADMIN` | `true` | First user gets admin role |
| `ENABLE_CORS` | `true` | Enable CORS headers |
| `ALLOWED_ORIGINS` | `*` | Comma-separated origins |
| `MIN_PASSWORD_LENGTH` | `8` | Minimum password length |
| `REQUIRE_SPECIAL_CHARS` | `true` | Require special chars in passwords |

## Admin Panel

A built-in web-based admin panel is available at `/admin`. It provides a full graphical interface for managing your API:

- **Dashboard** — Overview of tables, users, and quick actions
- **Tables** — Create new tables, view/modify structure, add/drop columns
- **Data** — Browse records with pagination, search/filter, create, edit, and delete
- **Users** — Manage users, roles, and active status (admin only)
- **API Keys** — Generate, view, and revoke API keys
- **Queues** — Create and manage message queues, view messages and stats
- **API Explorer** — Interactive Swagger-style endpoint tester

Features:
- Bootstrap 5.3 responsive design with dark/light theme toggle
- Single-page application using fetch API (no page reloads)
- Works on any PHP hosting (no Node.js or build step required)
- JWT-based session with automatic token refresh

Simply navigate to `http://your-host/admin` after starting the server.

## Authentication

### JWT (Default)

Login to get a token, then include it in requests:

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Use token
curl http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN"

# Refresh token
curl -X POST http://localhost:8000/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"YOUR_REFRESH_TOKEN"}'
```

### API Key

Set `AUTH_METHOD=apikey` in `.env`. Create keys via API:

```bash
# Create an API key (requires JWT auth)
curl -X POST http://localhost:8000/api/v1/auth/apikey \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"My App Key"}'

# Use the key
curl http://localhost:8000/api/v1/products \
  -H "X-API-Key: ak_live_..."
```

### Basic Auth

Set `AUTH_METHOD=basic` in `.env`:

```bash
curl http://localhost:8000/api/v1/products \
  -u "admin@example.com:password"
```

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/v1/auth/login` | Login | No |
| `POST` | `/api/v1/auth/register` | Register user | No (first) / Admin |
| `POST` | `/api/v1/auth/refresh` | Refresh JWT token | No |
| `POST` | `/api/v1/auth/apikey` | Create API key | Yes |
| `GET` | `/api/v1/auth/apikeys` | List API keys | Yes |
| `DELETE` | `/api/v1/auth/apikey/{id}` | Revoke API key | Yes |

### User Management (Admin Only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/users` | List users |
| `POST` | `/api/v1/users` | Create user |
| `GET` | `/api/v1/users/{id}` | Get user |
| `PATCH` | `/api/v1/users/{id}` | Update user |
| `DELETE` | `/api/v1/users/{id}` | Delete user |

### Schema Management (Read: All / Write: Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/schema/tables` | List all tables |
| `POST` | `/api/v1/schema/tables` | Create a new table |
| `GET` | `/api/v1/schema/tables/{table}` | Get table columns |
| `DELETE` | `/api/v1/schema/tables/{table}` | Drop a table |
| `POST` | `/api/v1/schema/tables/{table}/columns` | Add a column |
| `PATCH` | `/api/v1/schema/tables/{table}/columns/{col}` | Modify a column |
| `DELETE` | `/api/v1/schema/tables/{table}/columns/{col}` | Drop a column |

**Create a table via API:**

```bash
curl -X POST http://localhost:8000/api/v1/schema/tables \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "table": "products",
    "columns": {
      "id":         {"type": "INT", "auto_increment": true, "primary": true},
      "name":       {"type": "VARCHAR", "length": 255, "nullable": false},
      "price":      {"type": "DECIMAL", "precision": 10, "scale": 2},
      "category":   {"type": "VARCHAR", "length": 100, "index": true},
      "created_at": {"type": "TIMESTAMP", "default": "CURRENT_TIMESTAMP"}
    }
  }'
```

**Add a column:**

```bash
curl -X POST http://localhost:8000/api/v1/schema/tables/products/columns \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"column": "description", "definition": {"type": "TEXT", "nullable": true}}'
```

**Modify a column:**

```bash
curl -X PATCH http://localhost:8000/api/v1/schema/tables/products/columns/name \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"definition": {"type": "VARCHAR", "length": 500, "nullable": false}}'
```

**Drop a column:**

```bash
curl -X DELETE http://localhost:8000/api/v1/schema/tables/products/columns/description \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Queues

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/api/v1/queues` | List queues | All |
| `POST` | `/api/v1/queues` | Create queue | Admin |
| `GET` | `/api/v1/queues/{queue}` | Get queue details | All |
| `PATCH` | `/api/v1/queues/{queue}` | Update queue | Admin |
| `DELETE` | `/api/v1/queues/{queue}` | Delete queue | Admin |
| `POST` | `/api/v1/queues/{queue}/messages` | Publish message | All |
| `GET` | `/api/v1/queues/{queue}/messages` | List messages | All |
| `GET` | `/api/v1/queues/{queue}/consume` | Consume next (pull) | All |
| `POST` | `/api/v1/queues/{queue}/messages/{id}/ack` | Acknowledge | All |
| `POST` | `/api/v1/queues/{queue}/messages/{id}/nack` | Reject | All |
| `POST` | `/api/v1/queues/{queue}/messages/{id}/retry` | Retry dead | Admin |

See [QUEUES.md](QUEUES.md) for full documentation.

### Generic CRUD

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/{table}` | List records (paginated) |
| `GET` | `/api/v1/{table}/{id}` | Get record by ID |
| `GET` | `/api/v1/{table}/filter` | Filter records |
| `POST` | `/api/v1/{table}` | Create record |
| `PATCH` | `/api/v1/{table}/{id}` | Update record |
| `DELETE` | `/api/v1/{table}/{id}` | Delete record |

### Pagination

List endpoints support pagination via query parameters:

```
GET /api/v1/products?page=2&per_page=20&order_by=price&order_dir=DESC
```

### Filtering

Use the filter endpoint with operator syntax:

```
GET /api/v1/products/filter?price[gte]=100&category=Electronics&order=price:desc
```

Supported operators: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`

### Response Format

All responses follow a standard JSON format:

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 10,
    "total_pages": 10,
    "timestamp": "2024-02-07T10:30:00Z"
  },
  "errors": []
}
```

Error responses:

```json
{
  "success": false,
  "data": null,
  "meta": {
    "timestamp": "2024-02-07T10:30:00Z"
  },
  "errors": [
    { "message": "Validation failed" },
    { "field": "email", "message": "The email field is required." }
  ]
}
```

## Schema Management

Create tables programmatically:

```php
use Kunlare\PhpCrudApi\Database\SchemaBuilder;

$schema = new SchemaBuilder($connection);

// Create table
$schema->createTable('products', [
    'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
    'name' => ['type' => 'VARCHAR', 'length' => 255, 'nullable' => false],
    'price' => ['type' => 'DECIMAL', 'precision' => 10, 'scale' => 2],
    'category_id' => [
        'type' => 'INT',
        'foreign_key' => ['table' => 'categories', 'column' => 'id'],
    ],
    'status' => ['type' => 'ENUM', 'values' => ['active', 'inactive'], 'default' => 'active'],
    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
]);

// Add column
$schema->addColumn('products', 'sku', [
    'type' => 'VARCHAR', 'length' => 50, 'unique' => true,
]);

// Check table existence
if ($schema->tableExists('products')) {
    $columns = $schema->getTableColumns('products');
}

// Drop table
$schema->dropTable('products');
```

## Query Builder

Use the query builder for custom queries:

```php
use Kunlare\PhpCrudApi\Database\QueryBuilder;

$qb = new QueryBuilder($connection);

// Select with conditions
$products = $qb->select('products')
    ->where('price', '>=', 100)
    ->whereLike('name', '%laptop%')
    ->orderBy('price', 'DESC')
    ->limit(10)
    ->get();

// Insert
$id = $qb->insert('products', [
    'name' => 'New Product',
    'price' => 49.99,
]);

// Update
$qb->where('id', '=', 1)->update('products', ['price' => 39.99]);

// Delete
$qb->where('id', '=', 1)->delete('products');

// Apply filters from array
$results = $qb->applyFilters('products', [
    'price' => ['operator' => '>=', 'value' => 50],
    'status' => ['operator' => '=', 'value' => 'active'],
])->get();
```

## Testing

```bash
composer test
```

## Security

- All SQL queries use **PDO prepared statements** to prevent SQL injection
- Passwords are hashed with **bcrypt** via `password_hash()`
- JWT tokens are signed with configurable algorithms
- API keys are stored as hashed values (SHA-256 by default)
- CORS is configurable with allowed origins, methods, and headers
- Input validation prevents malformed data from reaching the database
- Security headers (`X-Content-Type-Options`, `X-Frame-Options`) are sent with responses

## Troubleshooting

**Database connection failed**
- Verify `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` in `.env`
- Ensure the database exists and the user has permissions

**401 Unauthorized**
- Check that your token hasn't expired
- Verify the `Authorization: Bearer <token>` header is correct
- For API key auth, check the `X-API-Key` header

**Table not found**
- Ensure `AUTO_SETUP=true` in `.env`, or run the SQL in `examples/database.sql`
- Create tables using `SchemaBuilder` or directly in MySQL

**Debug mode**
- Set `API_DEBUG=true` in `.env` to see detailed error messages (disable in production)

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add my feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request

Please follow PSR-12 coding standards and include tests for new features.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.
