<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Api;

use Kunlare\PhpApiEngine\Api\Middleware\AuthMiddleware;
use Kunlare\PhpApiEngine\Api\Middleware\CorsMiddleware;
use Kunlare\PhpApiEngine\Auth\AuthManager;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Crud\AuthController;
use Kunlare\PhpApiEngine\Crud\CrudController;
use Kunlare\PhpApiEngine\Crud\QueueController;
use Kunlare\PhpApiEngine\Crud\SchemaController;
use Kunlare\PhpApiEngine\Crud\UserController;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Ui\AdminPanel;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\DatabaseException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;
use Throwable;

/**
 * RESTful router that dispatches requests to appropriate controllers.
 */
class Router
{
    /** @var Connection Database connection */
    private Connection $connection;

    /** @var Config Configuration */
    private Config $config;

    /** @var AuthManager Auth manager */
    private AuthManager $authManager;

    /** @var string API base path prefix */
    private string $prefix;

    /**
     * @param Connection $connection Database connection
     * @param Config $config Configuration
     */
    public function __construct(Connection $connection, Config $config)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->authManager = new AuthManager($connection, $config);

        $basePath = rtrim($config->getString('API_BASE_PATH', '/api'), '/');
        $version = $config->getString('API_VERSION', 'v1');
        $this->prefix = $basePath . '/' . $version;
    }

    /**
     * Handle the incoming HTTP request.
     */
    public function handleRequest(): void
    {
        $request = new Request();

        // Serve admin panel (not an API call)
        $uri = $request->getUri();
        if ($uri === '/admin' || str_starts_with($uri, '/admin/')) {
            $panel = new AdminPanel($this->config);
            $panel->render();
            return;
        }

        try {
            // Apply CORS middleware
            $cors = new CorsMiddleware($this->config);
            if (!$cors->handle($request)) {
                return; // Preflight handled
            }

            // Apply Auth middleware
            $auth = new AuthMiddleware($this->authManager, $this->config);
            $auth->handle($request);

            // Route the request
            $this->dispatch($request);
        } catch (AuthException $e) {
            Response::error($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), $e->getStatusCode(), $this->formatValidationErrors($e));
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), $e->getStatusCode());
        } catch (DatabaseException $e) {
            $message = $this->config->getBool('API_DEBUG', false)
                ? $e->getMessage()
                : 'Internal server error';
            Response::error($message, $e->getStatusCode());
        } catch (Throwable $e) {
            $message = $this->config->getBool('API_DEBUG', false)
                ? $e->getMessage()
                : 'Internal server error';
            Response::error($message, 500);
        }
    }

    /**
     * Dispatch a request to the appropriate controller.
     *
     * @param Request $request HTTP request
     * @throws NotFoundException
     */
    private function dispatch(Request $request): void
    {
        $uri = $request->getUri();
        $method = $request->getMethod();

        // Strip the API prefix
        if (!str_starts_with($uri, $this->prefix)) {
            throw new NotFoundException("Endpoint not found: {$uri}");
        }

        $path = substr($uri, strlen($this->prefix));
        $path = '/' . trim($path, '/');
        $segments = array_values(array_filter(explode('/', $path)));

        // Route: /auth/*
        if (isset($segments[0]) && $segments[0] === 'auth') {
            $this->routeAuth($request, $method, $segments);
            return;
        }

        // Route: /schema/*
        if (isset($segments[0]) && $segments[0] === 'schema') {
            $this->routeSchema($request, $method, $segments);
            return;
        }

        // Route: /queues/*
        if (isset($segments[0]) && $segments[0] === 'queues') {
            $this->routeQueues($request, $method, $segments);
            return;
        }

        // Route: /users/*
        if (isset($segments[0]) && $segments[0] === 'users') {
            $this->routeUsers($request, $method, $segments);
            return;
        }

        // Route: /{table}/* (generic CRUD)
        if (isset($segments[0])) {
            $this->routeCrud($request, $method, $segments);
            return;
        }

        throw new NotFoundException('Endpoint not found');
    }

    /**
     * Route authentication endpoints.
     *
     * @param Request $request HTTP request
     * @param string $method HTTP method
     * @param array<string> $segments URL segments
     * @throws NotFoundException
     */
    private function routeAuth(Request $request, string $method, array $segments): void
    {
        $controller = new AuthController(
            $this->connection,
            $request,
            $this->config,
            $this->authManager
        );

        $action = $segments[1] ?? '';

        match (true) {
            $method === 'POST' && $action === 'login' => $controller->login(),
            $method === 'POST' && $action === 'register' => $controller->register(),
            $method === 'POST' && $action === 'refresh' => $controller->refresh(),
            $method === 'GET' && $action === 'profile' => $controller->getProfile(),
            $method === 'PATCH' && $action === 'profile' => $controller->updateProfile(),
            $method === 'POST' && $action === 'apikey' => $controller->createApiKey(),
            $method === 'GET' && $action === 'apikeys' && isset($segments[2]) && $segments[2] === 'all'
                => $controller->listAllApiKeys(),
            $method === 'GET' && $action === 'apikeys' => $controller->listApiKeys(),
            $method === 'DELETE' && $action === 'apikey' && isset($segments[2])
                => $controller->revokeApiKey((int) $segments[2]),
            default => throw new NotFoundException('Auth endpoint not found'),
        };
    }

    /**
     * Route user management endpoints.
     *
     * @param Request $request HTTP request
     * @param string $method HTTP method
     * @param array<string> $segments URL segments
     * @throws NotFoundException
     */
    private function routeUsers(Request $request, string $method, array $segments): void
    {
        $controller = new UserController(
            $this->connection,
            $request,
            $this->config
        );

        $id = isset($segments[1]) ? (int) $segments[1] : null;

        match (true) {
            $method === 'GET' && $id === null => $controller->list(),
            $method === 'POST' && $id === null => $controller->create(),
            $method === 'GET' && $id !== null => $controller->get($id),
            $method === 'PATCH' && $id !== null => $controller->update($id),
            $method === 'DELETE' && $id !== null => $controller->delete($id),
            default => throw new NotFoundException('User endpoint not found'),
        };
    }

    /**
     * Route schema management endpoints.
     *
     * @param Request $request HTTP request
     * @param string $method HTTP method
     * @param array<string> $segments URL segments
     * @throws NotFoundException
     */
    private function routeSchema(Request $request, string $method, array $segments): void
    {
        $controller = new SchemaController(
            $this->connection,
            $request,
            $this->config
        );

        $sub = $segments[1] ?? '';

        // /schema/tables
        if ($sub !== 'tables') {
            throw new NotFoundException('Schema endpoint not found');
        }

        $table = $segments[2] ?? null;
        $columnAction = $segments[3] ?? null;
        $column = $segments[4] ?? null;

        match (true) {
            // GET /schema/tables → list all tables
            $method === 'GET' && $table === null
                => $controller->listTables(),
            // POST /schema/tables → create a new table
            $method === 'POST' && $table === null
                => $controller->createTable(),
            // GET /schema/tables/{table} → get table columns
            $method === 'GET' && $table !== null && $columnAction === null
                => $controller->getTable($table),
            // DELETE /schema/tables/{table} → drop table
            $method === 'DELETE' && $table !== null && $columnAction === null
                => $controller->dropTable($table),
            // POST /schema/tables/{table}/columns → add column
            $method === 'POST' && $table !== null && $columnAction === 'columns' && $column === null
                => $controller->addColumn($table),
            // PATCH /schema/tables/{table}/columns/{col} → modify column
            $method === 'PATCH' && $table !== null && $columnAction === 'columns' && $column !== null
                => $controller->modifyColumn($table, $column),
            // DELETE /schema/tables/{table}/columns/{col} → drop column
            $method === 'DELETE' && $table !== null && $columnAction === 'columns' && $column !== null
                => $controller->dropColumn($table, $column),
            default => throw new NotFoundException('Schema endpoint not found'),
        };
    }

    /**
     * Route queue management endpoints.
     *
     * @param Request $request HTTP request
     * @param string $method HTTP method
     * @param array<string> $segments URL segments
     * @throws NotFoundException
     */
    private function routeQueues(Request $request, string $method, array $segments): void
    {
        $controller = new QueueController(
            $this->connection,
            $request,
            $this->config
        );

        $queueName = $segments[1] ?? null;
        $sub = $segments[2] ?? null;
        $messageId = isset($segments[3]) ? (int) $segments[3] : null;
        $action = $segments[4] ?? null;

        match (true) {
            // GET /queues → list queues
            $method === 'GET' && $queueName === null
                => $controller->listQueues(),
            // POST /queues → create queue
            $method === 'POST' && $queueName === null
                => $controller->createQueue(),
            // GET /queues/{queue}/consume → consume next message (pull)
            $method === 'GET' && $queueName !== null && $sub === 'consume'
                => $controller->consumeMessage($queueName),
            // POST /queues/{queue}/messages/{id}/ack → acknowledge
            $method === 'POST' && $queueName !== null && $sub === 'messages' && $messageId !== null && $action === 'ack'
                => $controller->ackMessage($queueName, $messageId),
            // POST /queues/{queue}/messages/{id}/nack → reject
            $method === 'POST' && $queueName !== null && $sub === 'messages' && $messageId !== null && $action === 'nack'
                => $controller->nackMessage($queueName, $messageId),
            // POST /queues/{queue}/messages/{id}/retry → retry dead
            $method === 'POST' && $queueName !== null && $sub === 'messages' && $messageId !== null && $action === 'retry'
                => $controller->retryMessage($queueName, $messageId),
            // POST /queues/{queue}/messages → publish message
            $method === 'POST' && $queueName !== null && $sub === 'messages' && $messageId === null
                => $controller->publishMessage($queueName),
            // GET /queues/{queue}/messages → list messages
            $method === 'GET' && $queueName !== null && $sub === 'messages' && $messageId === null
                => $controller->listMessages($queueName),
            // GET /queues/{queue}/messages/{id} → get message
            $method === 'GET' && $queueName !== null && $sub === 'messages' && $messageId !== null && $action === null
                => $controller->getMessage($queueName, $messageId),
            // DELETE /queues/{queue}/messages/{id} → cancel message
            $method === 'DELETE' && $queueName !== null && $sub === 'messages' && $messageId !== null
                => $controller->cancelMessage($queueName, $messageId),
            // GET /queues/{queue} → get queue details
            $method === 'GET' && $queueName !== null && $sub === null
                => $controller->getQueue($queueName),
            // PATCH /queues/{queue} → update queue
            $method === 'PATCH' && $queueName !== null && $sub === null
                => $controller->updateQueue($queueName),
            // DELETE /queues/{queue} → delete queue
            $method === 'DELETE' && $queueName !== null && $sub === null
                => $controller->deleteQueue($queueName),
            default => throw new NotFoundException('Queue endpoint not found'),
        };
    }

    /**
     * Route generic CRUD endpoints.
     *
     * @param Request $request HTTP request
     * @param string $method HTTP method
     * @param array<string> $segments URL segments
     * @throws NotFoundException
     */
    private function routeCrud(Request $request, string $method, array $segments): void
    {
        $table = $segments[0];
        $controller = new CrudController($this->connection, $request);

        $idOrAction = $segments[1] ?? null;

        // Check if second segment is 'filter'
        if ($idOrAction === 'filter') {
            if ($method === 'GET') {
                $controller->filter($table);
                return;
            }
            throw new NotFoundException('Endpoint not found');
        }

        match (true) {
            $method === 'GET' && $idOrAction === null => $controller->list($table),
            $method === 'GET' && $idOrAction !== null => $controller->get($table, $idOrAction),
            $method === 'POST' && $idOrAction === null => $controller->create($table),
            $method === 'PATCH' && $idOrAction !== null => $controller->update($table, $idOrAction),
            $method === 'DELETE' && $idOrAction !== null => $controller->delete($table, $idOrAction),
            default => throw new NotFoundException('Endpoint not found'),
        };
    }

    /**
     * Format validation exception errors for response.
     *
     * @param ValidationException $e Validation exception
     * @return array<mixed>
     */
    private function formatValidationErrors(ValidationException $e): array
    {
        $errors = [];
        foreach ($e->getErrors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['field' => $field, 'message' => $message];
            }
        }
        return $errors;
    }
}
