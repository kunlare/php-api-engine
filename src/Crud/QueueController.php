<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Api\Response;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;

/**
 * Controller for queue management and message operations.
 *
 * Queue endpoints:
 *   GET    /queues                           → list all queues
 *   POST   /queues                           → create a queue
 *   GET    /queues/{queue}                   → get queue details
 *   PATCH  /queues/{queue}                   → update a queue
 *   DELETE /queues/{queue}                   → delete a queue
 *
 * Message endpoints:
 *   POST   /queues/{queue}/messages          → publish a message
 *   GET    /queues/{queue}/messages          → list messages
 *   GET    /queues/{queue}/messages/{id}     → get message
 *   DELETE /queues/{queue}/messages/{id}     → cancel a pending message
 *   GET    /queues/{queue}/consume           → consume next message (pull)
 *   POST   /queues/{queue}/messages/{id}/ack  → acknowledge message
 *   POST   /queues/{queue}/messages/{id}/nack → reject message
 *   POST   /queues/{queue}/messages/{id}/retry → retry a dead message
 */
class QueueController
{
    private Connection $connection;
    private Request $request;
    private string $queuesTable;
    private string $messagesTable;

    public function __construct(Connection $connection, Request $request, Config $config)
    {
        $this->connection = $connection;
        $this->request = $request;
        $this->queuesTable = $config->getString('QUEUES_TABLE', 'queues');
        $this->messagesTable = $config->getString('QUEUE_MESSAGES_TABLE', 'queue_messages');
    }

    /* =========================================================
       Queue CRUD
       ========================================================= */

    /**
     * List all queues with message counts.
     */
    public function listQueues(): void
    {
        $this->requireAuth();

        $queues = $this->connection->fetchAll(
            "SELECT q.*,
                    COUNT(m.id) AS total_messages,
                    SUM(CASE WHEN m.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN m.status = 'processing' THEN 1 ELSE 0 END) AS processing_count,
                    SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                    SUM(CASE WHEN m.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                    SUM(CASE WHEN m.status = 'dead' THEN 1 ELSE 0 END) AS dead_count
             FROM `{$this->queuesTable}` q
             LEFT JOIN `{$this->messagesTable}` m ON q.id = m.queue_id
             GROUP BY q.id
             ORDER BY q.name"
        );

        Response::success($queues);
    }

    /**
     * Create a new queue.
     */
    public function createQueue(): void
    {
        $this->requireAdmin();

        $data = $this->request->getJson();

        if (empty($data['name'])) {
            throw new ValidationException('The "name" field is required');
        }

        $name = trim($data['name']);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-]*$/', $name)) {
            throw new ValidationException('Queue name must be alphanumeric with underscores/hyphens');
        }

        $direction = $data['direction'] ?? 'inbound';
        $delivery = $data['delivery'] ?? ($direction === 'inbound' ? 'handler' : 'pull');

        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            throw new ValidationException('Direction must be "inbound" or "outbound"');
        }
        if (!in_array($delivery, ['handler', 'pull', 'push'], true)) {
            throw new ValidationException('Delivery must be "handler", "pull", or "push"');
        }

        if ($delivery === 'push' && empty($data['delivery_url'])) {
            throw new ValidationException('delivery_url is required for push delivery');
        }

        // Check uniqueness
        $existing = $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->queuesTable}` WHERE name = ?",
            [$name]
        );
        if ($existing > 0) {
            throw new ValidationException("Queue '{$name}' already exists");
        }

        $this->connection->execute(
            "INSERT INTO `{$this->queuesTable}`
             (name, direction, delivery, delivery_url, delivery_headers, secret, description, max_attempts, retry_delay, visibility_timeout)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $name,
                $direction,
                $delivery,
                $data['delivery_url'] ?? null,
                isset($data['delivery_headers']) ? json_encode($data['delivery_headers']) : null,
                $data['secret'] ?? null,
                $data['description'] ?? null,
                (int) ($data['max_attempts'] ?? 3),
                (int) ($data['retry_delay'] ?? 30),
                (int) ($data['visibility_timeout'] ?? 60),
            ]
        );

        $id = (int) $this->connection->lastInsertId();
        $queue = $this->connection->fetch(
            "SELECT * FROM `{$this->queuesTable}` WHERE id = ?",
            [$id]
        );

        Response::created($queue);
    }

    /**
     * Get a single queue with stats.
     */
    public function getQueue(string $name): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($name);

        $stats = $this->connection->fetch(
            "SELECT
                COUNT(*) AS total_messages,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'dead' THEN 1 ELSE 0 END) AS dead_count
             FROM `{$this->messagesTable}` WHERE queue_id = ?",
            [$queue['id']]
        );

        $queue['stats'] = $stats;
        Response::success($queue);
    }

    /**
     * Update a queue.
     */
    public function updateQueue(string $name): void
    {
        $this->requireAdmin();

        $queue = $this->findQueue($name);
        $data = $this->request->getJson();

        $allowed = ['description', 'delivery_url', 'delivery_headers', 'secret', 'max_attempts', 'retry_delay', 'visibility_timeout', 'is_active'];
        $sets = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`{$field}` = ?";
                if ($field === 'delivery_headers') {
                    $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                } elseif ($field === 'is_active') {
                    $params[] = $data[$field] ? 1 : 0;
                } elseif (in_array($field, ['max_attempts', 'retry_delay', 'visibility_timeout'], true)) {
                    $params[] = (int) $data[$field];
                } else {
                    $params[] = $data[$field];
                }
            }
        }

        if (empty($sets)) {
            throw new ValidationException('No valid fields to update');
        }

        $params[] = $queue['id'];
        $this->connection->execute(
            "UPDATE `{$this->queuesTable}` SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );

        $updated = $this->connection->fetch(
            "SELECT * FROM `{$this->queuesTable}` WHERE id = ?",
            [$queue['id']]
        );

        Response::success($updated);
    }

    /**
     * Delete a queue and all its messages.
     */
    public function deleteQueue(string $name): void
    {
        $this->requireAdmin();

        $queue = $this->findQueue($name);

        $this->connection->execute(
            "DELETE FROM `{$this->queuesTable}` WHERE id = ?",
            [$queue['id']]
        );

        Response::success(['message' => "Queue '{$name}' deleted successfully"]);
    }

    /* =========================================================
       Message Operations
       ========================================================= */

    /**
     * Publish a message to a queue.
     */
    public function publishMessage(string $queueName): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);

        if (!$queue['is_active']) {
            throw new ValidationException("Queue '{$queueName}' is not active");
        }

        $data = $this->request->getJson();

        $payload = $data['payload'] ?? $data;
        // If 'payload' was a key, remove meta fields from top level
        if (isset($data['payload'])) {
            $priority = (int) ($data['priority'] ?? 0);
            $delay = (int) ($data['delay'] ?? 0);
        } else {
            $priority = 0;
            $delay = 0;
        }

        $availableAt = $delay > 0
            ? date('Y-m-d H:i:s', time() + $delay)
            : date('Y-m-d H:i:s');

        $this->connection->execute(
            "INSERT INTO `{$this->messagesTable}`
             (queue_id, payload, priority, max_attempts, available_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                $queue['id'],
                json_encode($payload),
                $priority,
                $queue['max_attempts'],
                $availableAt,
            ]
        );

        $id = (int) $this->connection->lastInsertId();
        $message = $this->connection->fetch(
            "SELECT * FROM `{$this->messagesTable}` WHERE id = ?",
            [$id]
        );

        Response::created($message);
    }

    /**
     * List messages in a queue with optional status filter.
     */
    public function listMessages(string $queueName): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);

        $page = max(1, (int) ($this->request->getQueryParam('page', 1)));
        $perPage = min(100, max(1, (int) ($this->request->getQueryParam('per_page', 20))));
        $status = $this->request->getQueryParam('status');
        $offset = ($page - 1) * $perPage;

        $where = "WHERE queue_id = ?";
        $params = [$queue['id']];

        if ($status && in_array($status, ['pending', 'processing', 'completed', 'failed', 'dead'], true)) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        $total = (int) $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->messagesTable}` {$where}",
            $params
        );

        $messages = $this->connection->fetchAll(
            "SELECT * FROM `{$this->messagesTable}` {$where} ORDER BY priority DESC, id ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        Response::success($messages, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Get a single message.
     */
    public function getMessage(string $queueName, int $messageId): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);
        $message = $this->connection->fetch(
            "SELECT * FROM `{$this->messagesTable}` WHERE id = ? AND queue_id = ?",
            [$messageId, $queue['id']]
        );

        if ($message === null) {
            throw new NotFoundException("Message {$messageId} not found in queue '{$queueName}'");
        }

        Response::success($message);
    }

    /**
     * Cancel a pending message.
     */
    public function cancelMessage(string $queueName, int $messageId): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);
        $message = $this->connection->fetch(
            "SELECT * FROM `{$this->messagesTable}` WHERE id = ? AND queue_id = ?",
            [$messageId, $queue['id']]
        );

        if ($message === null) {
            throw new NotFoundException("Message {$messageId} not found in queue '{$queueName}'");
        }

        if ($message['status'] !== 'pending') {
            throw new ValidationException("Only pending messages can be cancelled (current: {$message['status']})");
        }

        $this->connection->execute(
            "DELETE FROM `{$this->messagesTable}` WHERE id = ?",
            [$messageId]
        );

        Response::success(['message' => 'Message cancelled']);
    }

    /**
     * Consume the next message from a pull queue.
     */
    public function consumeMessage(string $queueName): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);

        if ($queue['delivery'] !== 'pull') {
            throw new ValidationException("Only pull-delivery queues support consume. This queue uses '{$queue['delivery']}' delivery.");
        }

        // Release timed-out processing messages back to pending
        $timeout = (int) $queue['visibility_timeout'];
        $this->connection->execute(
            "UPDATE `{$this->messagesTable}`
             SET status = 'pending', started_at = NULL
             WHERE queue_id = ? AND status = 'processing'
               AND started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$queue['id'], $timeout]
        );

        // Fetch and lock the next pending message
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
                Response::success(null, ['message' => 'No messages available']);
                return;
            }

            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'processing', started_at = NOW(), attempts = attempts + 1
                 WHERE id = ?",
                [$message['id']]
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }

        // Return updated message
        $message = $this->connection->fetch(
            "SELECT * FROM `{$this->messagesTable}` WHERE id = ?",
            [$message['id']]
        );

        Response::success($message);
    }

    /**
     * Acknowledge (complete) a message.
     */
    public function ackMessage(string $queueName, int $messageId): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);
        $message = $this->findMessage($queue, $messageId);

        if ($message['status'] !== 'processing') {
            throw new ValidationException("Only processing messages can be acknowledged (current: {$message['status']})");
        }

        $this->connection->execute(
            "UPDATE `{$this->messagesTable}`
             SET status = 'completed', completed_at = NOW()
             WHERE id = ?",
            [$messageId]
        );

        Response::success(['message' => 'Message acknowledged']);
    }

    /**
     * Reject (nack) a message — retry or mark as dead.
     */
    public function nackMessage(string $queueName, int $messageId): void
    {
        $this->requireAuth();

        $queue = $this->findQueue($queueName);
        $message = $this->findMessage($queue, $messageId);

        if ($message['status'] !== 'processing') {
            throw new ValidationException("Only processing messages can be rejected (current: {$message['status']})");
        }

        $data = $this->request->getJson();
        $error = $data['error'] ?? 'Rejected by consumer';

        if ($message['attempts'] >= $message['max_attempts']) {
            // Max attempts exceeded → dead letter
            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'dead', error = ?, completed_at = NOW()
                 WHERE id = ?",
                [$error, $messageId]
            );
            Response::success(['message' => 'Message moved to dead letter', 'status' => 'dead']);
        } else {
            // Retry with backoff
            $delay = (int) $queue['retry_delay'] * (int) pow(2, $message['attempts'] - 1);
            $this->connection->execute(
                "UPDATE `{$this->messagesTable}`
                 SET status = 'pending', started_at = NULL, error = ?,
                     available_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                 WHERE id = ?",
                [$error, $delay, $messageId]
            );
            Response::success(['message' => 'Message returned to queue for retry', 'status' => 'pending', 'retry_in' => $delay]);
        }
    }

    /**
     * Retry a dead message (admin only).
     */
    public function retryMessage(string $queueName, int $messageId): void
    {
        $this->requireAdmin();

        $queue = $this->findQueue($queueName);
        $message = $this->findMessage($queue, $messageId);

        if ($message['status'] !== 'dead' && $message['status'] !== 'failed') {
            throw new ValidationException("Only dead or failed messages can be retried (current: {$message['status']})");
        }

        $this->connection->execute(
            "UPDATE `{$this->messagesTable}`
             SET status = 'pending', started_at = NULL, completed_at = NULL,
                 error = NULL, attempts = 0, available_at = NOW()
             WHERE id = ?",
            [$messageId]
        );

        Response::success(['message' => 'Message queued for retry', 'status' => 'pending']);
    }

    /* =========================================================
       Helpers
       ========================================================= */

    /** @return array<string, mixed> */
    private function findQueue(string $name): array
    {
        $queue = $this->connection->fetch(
            "SELECT * FROM `{$this->queuesTable}` WHERE name = ?",
            [$name]
        );

        if ($queue === null) {
            throw new NotFoundException("Queue '{$name}' not found");
        }

        return $queue;
    }

    /**
     * @param array<string, mixed> $queue
     * @return array<string, mixed>
     */
    private function findMessage(array $queue, int $messageId): array
    {
        $message = $this->connection->fetch(
            "SELECT * FROM `{$this->messagesTable}` WHERE id = ? AND queue_id = ?",
            [$messageId, $queue['id']]
        );

        if ($message === null) {
            throw new NotFoundException("Message {$messageId} not found in queue '{$queue['name']}'");
        }

        return $message;
    }

    private function requireAuth(): void
    {
        $user = $this->request->getUser();
        if ($user === null) {
            throw new AuthException('Authentication required', 401);
        }
    }

    private function requireAdmin(): void
    {
        $user = $this->request->getUser();
        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            throw new AuthException('Forbidden: Admin access required', 403);
        }
    }
}
