<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Crud\QueueController;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;
use Kunlare\PhpApiEngine\Tests\TestCase;

class QueueControllerTest extends TestCase
{
    private Connection $connection;
    private Request $request;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
        $this->request = $this->createMock(Request::class);
        $this->config = $this->createConfig();
    }

    private function createController(): QueueController
    {
        return new QueueController($this->connection, $this->request, $this->config);
    }

    /* ---------- Auth ---------- */

    public function testListQueuesRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Authentication required');
        $this->createController()->listQueues();
    }

    public function testCreateQueueRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'developer']);
        $this->expectException(AuthException::class);
        $this->createController()->createQueue();
    }

    public function testDeleteQueueRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'user']);
        $this->expectException(AuthException::class);
        $this->createController()->deleteQueue('test');
    }

    public function testUpdateQueueRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'developer']);
        $this->expectException(AuthException::class);
        $this->createController()->updateQueue('test');
    }

    public function testRetryMessageRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'developer']);
        $this->expectException(AuthException::class);
        $this->createController()->retryMessage('test', 1);
    }

    /* ---------- Queue CRUD validation ---------- */

    public function testCreateQueueRequiresName(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn([]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"name" field is required');
        $this->createController()->createQueue();
    }

    public function testCreateQueueRejectsInvalidName(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn(['name' => 'invalid name!']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('alphanumeric');
        $this->createController()->createQueue();
    }

    public function testCreateQueueRejectsInvalidDirection(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn(['name' => 'test', 'direction' => 'sideways']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Direction');
        $this->createController()->createQueue();
    }

    public function testCreateQueueRejectsInvalidDelivery(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn(['name' => 'test', 'delivery' => 'pigeon']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Delivery');
        $this->createController()->createQueue();
    }

    public function testCreateQueueRequiresUrlForPush(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn([
            'name' => 'test',
            'direction' => 'outbound',
            'delivery' => 'push',
        ]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('delivery_url');
        $this->createController()->createQueue();
    }

    /* ---------- Queue not found ---------- */

    public function testGetQueueNotFound(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetch')->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("'nonexistent' not found");
        $this->createController()->getQueue('nonexistent');
    }

    public function testPublishRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->publishMessage('test');
    }

    public function testConsumeRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->consumeMessage('test');
    }

    public function testAckRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->ackMessage('test', 1);
    }

    public function testNackRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->nackMessage('test', 1);
    }

    /* ---------- Consume validation ---------- */

    public function testConsumeRejectsNonPullQueue(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetch')->willReturn([
            'id' => 1, 'name' => 'test', 'delivery' => 'handler',
            'is_active' => 1, 'visibility_timeout' => 60,
        ]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pull-delivery');
        $this->createController()->consumeMessage('test');
    }

    /* ---------- Cancel validation ---------- */

    public function testCancelRejectsNonPending(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        // First fetch returns queue, second returns message
        $this->connection->method('fetch')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'name' => 'test', 'delivery' => 'pull', 'is_active' => 1],
            ['id' => 5, 'queue_id' => 1, 'status' => 'processing']
        );
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only pending');
        $this->createController()->cancelMessage('test', 5);
    }

    /* ---------- Ack validation ---------- */

    public function testAckRejectsNonProcessing(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetch')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'name' => 'test'],
            ['id' => 5, 'queue_id' => 1, 'status' => 'pending']
        );
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only processing');
        $this->createController()->ackMessage('test', 5);
    }

    /* ---------- Retry validation ---------- */

    public function testRetryRejectsNonDeadOrFailed(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetch')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'name' => 'test'],
            ['id' => 5, 'queue_id' => 1, 'status' => 'pending']
        );
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only dead or failed');
        $this->createController()->retryMessage('test', 5);
    }

    public function testListMessagesRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->listMessages('test');
    }

    public function testGetMessageRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);
        $this->expectException(AuthException::class);
        $this->createController()->getMessage('test', 1);
    }
}
