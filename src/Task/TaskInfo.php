<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

/**
 * Information about a task's current state.
 */
final readonly class TaskInfo
{
    /**
     * @param 'pending'|'running'|'sleeping'|'completed'|'failed'|'cancelled' $state
     */
    public function __construct(
        public string $taskId,
        public string $taskName,
        public string $state,
        public int $attempts,
        public mixed $completedPayload = null,
    ) {}

    public function isCompleted(): bool
    {
        return $this->state === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->state === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->state === 'cancelled';
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, ['completed', 'failed', 'cancelled'], true);
    }
}
