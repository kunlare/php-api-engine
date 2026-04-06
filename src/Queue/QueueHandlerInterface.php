<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Queue;

/**
 * Interface for queue message handlers.
 *
 * Implement this interface and register it for a queue to process inbound messages.
 */
interface QueueHandlerInterface
{
    /**
     * Handle a queue message.
     *
     * @param array<string, mixed> $payload The decoded JSON payload
     * @throws \Throwable If processing fails (message will be retried or moved to dead letter)
     */
    public function handle(array $payload): void;
}
