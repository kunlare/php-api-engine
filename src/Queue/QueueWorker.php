<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Queue;

use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;

/**
 * Queue worker that processes messages from inbound (handler) and outbound (push) queues.
 *
 * Usage:
 *   $worker = new QueueWorker($connection, $config);
 *   $worker->registerHandler('emails', new EmailHandler());
 *   $worker->processQueue('emails', 10);  // process up to 10 messages
 */
class QueueWorker
{
    private Connection $connection;
    private string $queuesTable;
    private string $messagesTable;

    /** @var array<string, QueueHandlerInterface|callable> */
    private array $handlers = [];

    public function __construct(Connection $connection, Config $config)
    {
        $this->connection = $connection;
        $this->queuesTable = $config->getString('QUEUES_TABLE', 'queues');
        $this->messagesTable = $config->getString('QUEUE_MESSAGES_TABLE', 'queue_messages');
    }

    /**
     * Register a handler for a queue.
     *
     * @param string $queueName Queue name
     * @param QueueHandlerInterface|callable $handler Handler instance or callable
     */
    public function registerHandler(string $queueName, QueueHandlerInterface|callable $handler): void
    {
        $this->handlers[$queueName] = $handler;
    }

    /**
     * Process messages from a specific queue.
     *
     * @param string $queueName Queue name
     * @param int $batch Max messages to process in this run
     * @return array{processed: int, failed: int, dead: int} Processing stats
     */
    public function processQueue(string $queueName, int $batch = 10): array
    {
        $queue = $this->connection->fetch(
            "SELECT * FROM `{$this->queuesTable}` WHERE name = ? AND is_active = 1",
            [$queueName]
        );

        if ($queue === null) {
            throw new \RuntimeException("Queue '{$queueName}' not found or inactive");
        }

        // Release timed-out processing messages
        $timeout = (int) $queue['visibility_timeout'];
        $this->connection->execute(
            "UPDATE `{$this->messagesTable}`
             SET status = 'pending', started_at = NULL
             WHERE queue_id = ? AND status = 'processing'
               AND started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$queue['id'], $timeout]
        );

        $stats = ['processed' => 0, 'failed' => 0, 'dead' => 0];

        for ($i = 0; $i < $batch; $i++) {
            $message = $this->fetchNextMessage($queue);
            if ($message === null) {
                break; // No more messages
            }

            try {
                $this->processMessage($queue, $message);
                $this->markCompleted($message['id']);
                $stats['processed']++;
            } catch (\Throwable $e) {
                $this->handleFailure($queue, $message, $e->getMessage());
                if ($message['attempts'] >= $message['max_attempts']) {
                    $stats['dead']++;
                } else {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Process all active queues that have a handler or push delivery.
     *
     * @param int $batch Max messages per queue
     * @return array<string, array{processed: int, failed: int, dead: int}>
     */
    public function processAll(int $batch = 10): array
    {
        $queues = $this->connection->fetchAll(
            "SELECT * FROM `{$this->queuesTable}` WHERE is_active = 1 AND delivery IN ('handler', 'push')"
        );

        $results = [];
        foreach ($queues as $queue) {
            // Skip handler queues without registered handler
            if ($queue['delivery'] === 'handler' && !isset($this->handlers[$queue['name']])) {
                continue;
            }
            $results[$queue['name']] = $this->processQueue($queue['name'], $batch);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $queue
     * @return array<string, mixed>|null
     */
    private function fetchNextMessage(array $queue): ?array
    {
        $this->connection->beginTransaction();
        try {
            $message = $this->connection->fetch(
                "SELECT * FROM `{$this->messagesTable}`
                 WHERE queue_id = ? AND status = 'pending' AND available_at <= NOW()
                 ORDER BY priority DESC, id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
                [$queue['id']]
            );

            if ($message === null) {
                $this->connection->commit();
                return null;
            }

            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'processing', started_at = NOW(), attempts = attempts + 1
                 WHERE id = ?",
                [$message['id']]
            );

            $this->connection->commit();

            // Re-fetch to get updated values
            return $this->connection->fetch(
                "SELECT * FROM `{$this->messagesTable}` WHERE id = ?",
                [$message['id']]
            );
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $message
     */
    private function processMessage(array $queue, array $message): void
    {
        $payload = is_string($message['payload'])
            ? json_decode($message['payload'], true)
            : $message['payload'];

        if ($queue['delivery'] === 'push') {
            $this->pushMessage($queue, $payload);
        } elseif ($queue['delivery'] === 'handler') {
            $this->handleMessage($queue['name'], $payload);
        }
    }

    /** @param array<string, mixed> $payload */
    private function handleMessage(string $queueName, array $payload): void
    {
        $handler = $this->handlers[$queueName] ?? null;
        if ($handler === null) {
            throw new \RuntimeException("No handler registered for queue '{$queueName}'");
        }

        if ($handler instanceof QueueHandlerInterface) {
            $handler->handle($payload);
        } elseif (is_callable($handler)) {
            $handler($payload);
        }
    }

    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $payload
     */
    private function pushMessage(array $queue, array $payload): void
    {
        $url = $queue['delivery_url'];
        if (empty($url)) {
            throw new \RuntimeException("No delivery_url configured for push queue '{$queue['name']}'");
        }

        $body = json_encode($payload) ?: '{}';
        $headers = ['Content-Type: application/json'];

        // Add HMAC signature if secret is configured
        if (!empty($queue['secret'])) {
            $signature = hash_hmac('sha256', $body, $queue['secret']);
            $headers[] = 'X-Signature: sha256=' . $signature;
        }

        // Add custom headers
        if (!empty($queue['delivery_headers'])) {
            $custom = is_string($queue['delivery_headers'])
                ? json_decode($queue['delivery_headers'], true)
                : $queue['delivery_headers'];
            if (is_array($custom)) {
                foreach ($custom as $key => $value) {
                    $headers[] = "{$key}: {$value}";
                }
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Push delivery failed: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Push delivery returned HTTP {$httpCode}: " . substr((string) $response, 0, 500));
        }
    }

    private function markCompleted(int $messageId): void
    {
        $this->connection->execute(
            "UPDATE `{$this->messagesTable}`
             SET status = 'completed', completed_at = NOW()
             WHERE id = ?",
            [$messageId]
        );
    }

    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $message
     */
    private function handleFailure(array $queue, array $message, string $error): void
    {
        if ($message['attempts'] >= $message['max_attempts']) {
            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'dead', error = ?, completed_at = NOW()
                 WHERE id = ?",
                [$error, $message['id']]
            );
        } else {
            $delay = (int) $queue['retry_delay'] * (int) pow(2, $message['attempts'] - 1);
            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'failed', error = ?,
                     available_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                 WHERE id = ?",
                [$error, $delay, $message['id']]
            );
            // Move back to pending after marking failure
            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'pending', started_at = NULL
                 WHERE id = ? AND status = 'failed'",
                [$message['id']]
            );
        }
    }
}
