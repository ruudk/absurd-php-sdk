<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

/**
 * Represents a task claimed from the queue.
 *
 * @internal
 */
final class ClaimedTask
{
    /**
     * @param array<string, mixed>|null $headers
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $taskId,
        public readonly int $attempt,
        public readonly string $taskName,
        public readonly string $rawParams,
        public readonly ?string $retryStrategy,
        public readonly int $maxAttempts,
        public readonly ?array $headers,
        public ?string $wakeEvent,
        public mixed $eventPayload,
    ) {}
}
