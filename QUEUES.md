# Queue System

Database-backed message queue with support for inbound job processing, outbound pull consumption, and outbound push (webhook) delivery.

## Concepts

### Queue Types

| Direction | Delivery | Description | Who consumes |
|-----------|----------|-------------|--------------|
| inbound | handler | Job queue — messages processed by a PHP handler | Internal worker (cron) |
| outbound | pull | Buffer queue — external consumer polls for messages | External system via API |
| outbound | push | Webhook — worker delivers messages to a URL | External system receives POST |

### Message States

```
pending → processing → completed
                ↓
             failed → pending (retry, attempts < max)
                ↓
              dead   (max attempts reached)
```

- **pending** — waiting to be processed
- **processing** — picked up by a worker or consumer
- **completed** — successfully processed (ack)
- **failed** — processing failed, will retry
- **dead** — max retries exceeded, requires manual intervention

## API Endpoints

### Queue Management

| Method | Path | Access | Description |
|--------|------|--------|-------------|
| GET | `/queues` | All authenticated | List all queues with stats |
| POST | `/queues` | Admin | Create a new queue |
| GET | `/queues/{queue}` | All authenticated | Get queue details and stats |
| PATCH | `/queues/{queue}` | Admin | Update queue settings |
| DELETE | `/queues/{queue}` | Admin | Delete queue and all messages |

### Message Operations

| Method | Path | Access | Description |
|--------|------|--------|-------------|
| POST | `/queues/{queue}/messages` | All authenticated | Publish a message |
| GET | `/queues/{queue}/messages` | All authenticated | List messages (filterable) |
| GET | `/queues/{queue}/messages/{id}` | All authenticated | Get a single message |
| DELETE | `/queues/{queue}/messages/{id}` | All authenticated | Cancel a pending message |
| GET | `/queues/{queue}/consume` | All authenticated | Consume next message (pull only) |
| POST | `/queues/{queue}/messages/{id}/ack` | All authenticated | Acknowledge (complete) a message |
| POST | `/queues/{queue}/messages/{id}/nack` | All authenticated | Reject a message (retry or dead) |
| POST | `/queues/{queue}/messages/{id}/retry` | Admin | Retry a dead/failed message |

## Usage Examples

### 1. Inbound Queue (Job Processing)

Create the queue:
```bash
curl -X POST http://localhost:8000/api/v1/queues \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "emails", "direction": "inbound", "delivery": "handler"}'
```

Publish a message:
```bash
curl -X POST http://localhost:8000/api/v1/queues/emails/messages \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"payload": {"to": "user@example.com", "template": "welcome"}}'
```

Register a handler and run the worker:
```php
use Kunlare\PhpCrudApi\Queue\QueueWorker;
use Kunlare\PhpCrudApi\Queue\QueueHandlerInterface;

class EmailHandler implements QueueHandlerInterface
{
    public function handle(array $payload): void
    {
        mail($payload['to'], 'Welcome', 'Hello!');
    }
}

$worker = new QueueWorker($connection, $config);
$worker->registerHandler('emails', new EmailHandler());
$worker->processQueue('emails', 10);
```

Or use a closure:
```php
$worker->registerHandler('emails', function(array $payload) {
    mail($payload['to'], 'Welcome', 'Hello!');
});
```

Cron setup:
```
* * * * * php vendor/bin/queue-worker --queue=emails --batch=10
```

### 2. Outbound Pull Queue (External Consumer)

Create the queue:
```bash
curl -X POST http://localhost:8000/api/v1/queues \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "order-notifications", "direction": "outbound", "delivery": "pull"}'
```

Your application publishes when an order is created:
```bash
curl -X POST http://localhost:8000/api/v1/queues/order-notifications/messages \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"payload": {"event": "order.created", "order_id": 42, "total": 150.00}}'
```

External CRM consumes:
```bash
# Fetch next message
curl http://localhost:8000/api/v1/queues/order-notifications/consume \
  -H "X-API-Key: $CRM_API_KEY"

# Response: {"success": true, "data": {"id": 7, "payload": {...}, "status": "processing"}}

# Acknowledge after processing
curl -X POST http://localhost:8000/api/v1/queues/order-notifications/messages/7/ack \
  -H "X-API-Key: $CRM_API_KEY"
```

If the consumer fails:
```bash
# Reject and retry
curl -X POST http://localhost:8000/api/v1/queues/order-notifications/messages/7/nack \
  -H "X-API-Key: $CRM_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"error": "CRM temporarily unavailable"}'
```

### 3. Outbound Push Queue (Webhook)

Create the queue:
```bash
curl -X POST http://localhost:8000/api/v1/queues \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "order-webhooks",
    "direction": "outbound",
    "delivery": "push",
    "delivery_url": "https://crm.example.com/webhook",
    "secret": "my_hmac_secret",
    "delivery_headers": {"X-Source": "my-app"}
  }'
```

Publish a message (it will be delivered automatically by the worker):
```bash
curl -X POST http://localhost:8000/api/v1/queues/order-webhooks/messages \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"payload": {"event": "order.created", "order_id": 42}}'
```

The worker delivers via HTTP POST:
```
POST https://crm.example.com/webhook
Content-Type: application/json
X-Signature: sha256=<HMAC of body with secret>
X-Source: my-app

{"event": "order.created", "order_id": 42}
```

Run the worker via cron:
```
* * * * * php vendor/bin/queue-worker --queue=order-webhooks --batch=10
```

## Publishing Options

```json
{
  "payload": {"your": "data"},
  "priority": 10,
  "delay": 60
}
```

| Field | Type | Description |
|-------|------|-------------|
| payload | object | The message body (if omitted, entire body is the payload) |
| priority | int | Higher priority = processed first (default: 0) |
| delay | int | Delay in seconds before message becomes available (default: 0) |

## Queue Configuration

| Field | Default | Description |
|-------|---------|-------------|
| max_attempts | 3 | Max processing attempts before dead letter |
| retry_delay | 30 | Base delay in seconds for retry (exponential backoff: delay * 2^attempt) |
| visibility_timeout | 60 | Seconds before a processing message is released back (pull only) |
| delivery_url | — | Target URL for push delivery |
| delivery_headers | — | Custom HTTP headers for push delivery (JSON object) |
| secret | — | HMAC-SHA256 secret for signing push payloads |

## CLI Worker

```bash
# Process all active handler/push queues
php vendor/bin/queue-worker

# Process a specific queue
php vendor/bin/queue-worker --queue=emails

# Process up to 50 messages per queue
php vendor/bin/queue-worker --batch=50

# Show help
php vendor/bin/queue-worker --help
```

## Database Tables

The system uses two tables (auto-created by Bootstrap):

**queues** — Queue definitions
```sql
id, name, direction, delivery, delivery_url, delivery_headers,
secret, description, max_attempts, retry_delay, visibility_timeout,
is_active, created_at, updated_at
```

**queue_messages** — Messages with state tracking
```sql
id, queue_id, payload (JSON), status, priority, attempts,
max_attempts, available_at, started_at, completed_at, error,
created_at, updated_at
```

## Retry Strategy

Failed messages use exponential backoff:
- Attempt 1 fails → retry in `retry_delay` seconds
- Attempt 2 fails → retry in `retry_delay * 2` seconds
- Attempt 3 fails → retry in `retry_delay * 4` seconds
- After `max_attempts` → message moved to dead letter

Dead letter messages can be manually retried by an admin via the API or the admin panel.
