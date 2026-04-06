#!/usr/bin/env php
<?php

/**
 * Queue Worker CLI
 *
 * Processes messages from handler and push delivery queues.
 *
 * Usage:
 *   php vendor/bin/queue-worker                     # Process all active queues
 *   php vendor/bin/queue-worker --queue=emails       # Process a specific queue
 *   php vendor/bin/queue-worker --batch=20           # Process up to 20 messages per queue
 *   php vendor/bin/queue-worker --queue=emails --batch=50
 *
 * Cron example (run every minute):
 *   * * * * * php /path/to/vendor/bin/queue-worker --batch=10
 */

declare(strict_types=1);

// Find autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',       // When run from package root
    __DIR__ . '/../../../autoload.php',         // When installed as dependency
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, "Error: Could not find autoloader. Run 'composer install' first.\n");
    exit(1);
}

// Load .env if available
$envPaths = [getcwd(), __DIR__ . '/..', __DIR__ . '/../../..'];
foreach ($envPaths as $envPath) {
    if (file_exists($envPath . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();
        break;
    }
}

use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;
use Kunlare\PhpCrudApi\Queue\QueueWorker;

// Parse CLI arguments
$options = getopt('', ['queue:', 'batch:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
    Queue Worker - Processes messages from handler and push queues.

    Options:
      --queue=NAME    Process only the specified queue
      --batch=N       Max messages to process per queue (default: 10)
      --help          Show this help message

    HELP;
    exit(0);
}

$queueName = $options['queue'] ?? null;
$batch = (int) ($options['batch'] ?? 10);

try {
    $config = new Config();
    $connection = Connection::getInstance($config);
    $worker = new QueueWorker($connection, $config);

    $timestamp = date('Y-m-d H:i:s');

    if ($queueName) {
        echo "[{$timestamp}] Processing queue: {$queueName} (batch: {$batch})\n";
        $stats = $worker->processQueue($queueName, $batch);
        echo "  Processed: {$stats['processed']}, Failed: {$stats['failed']}, Dead: {$stats['dead']}\n";
    } else {
        echo "[{$timestamp}] Processing all active queues (batch: {$batch})\n";
        $results = $worker->processAll($batch);
        if (empty($results)) {
            echo "  No active queues with handlers found.\n";
        } else {
            foreach ($results as $name => $stats) {
                echo "  [{$name}] Processed: {$stats['processed']}, Failed: {$stats['failed']}, Dead: {$stats['dead']}\n";
            }
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
