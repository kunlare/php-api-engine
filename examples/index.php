<?php

declare(strict_types=1);

/**
 * Example entry point for the PHP CRUD API.
 *
 * Usage:
 *   1. Copy .env.example to .env and configure your database
 *   2. Run: php -S localhost:8000 examples/index.php
 *   3. Admin panel: http://localhost:8000/admin
 *   4. API access:  http://localhost:8000/api/v1/{table}
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kunlare\PhpCrudApi\Api\Router;
use Kunlare\PhpCrudApi\Bootstrap;
use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;

// Load configuration from the directory containing .env
$config = new Config(__DIR__);

// Initialize database connection
$db = Connection::getInstance($config);

// Bootstrap: create system tables if AUTO_SETUP is enabled
if ($config->getBool('AUTO_SETUP', true)) {
    $bootstrap = new Bootstrap($db, $config);
    $bootstrap->run();
}

// Create and handle the request
$router = new Router($db, $config);
$router->handleRequest();
