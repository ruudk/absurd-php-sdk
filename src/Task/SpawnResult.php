<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

/**
 * Result of spawning a task.
 */
final readonly class SpawnResult
{
    public function __construct(
        public string $taskId,
        public string $runId,
        public int $attempt,
        /**
         * True if the task was newly created, false if returned from idempotency cache.
         */
        public bool $created = true,
    ) {}
}
