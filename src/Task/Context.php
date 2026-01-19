<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use DateTimeImmutable;
use Fiber;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ruudk\Absurd\Exception\TimeoutError;
use Ruudk\Absurd\Execution\AwaitEventOptions;
use Ruudk\Absurd\Execution\Command\AwaitEvent;
use Ruudk\Absurd\Execution\Command\Checkpoint;
use Ruudk\Absurd\Execution\Command\EmitEvent;
use Ruudk\Absurd\Execution\Command\Heartbeat;
use Ruudk\Absurd\Execution\Command\SleepFor;
use Ruudk\Absurd\Execution\Command\SleepUntil;

/**
 * Context passed to task handlers with helper methods for workflow control.
 *
 * IMPORTANT: All methods in this class MUST be called from within a Fiber context
 * (i.e., during task execution). Calling these methods outside a Fiber will result
 * in a FiberError. The SDK automatically creates the Fiber context when executing
 * registered task handlers.
 */
final class Context
{
    private bool $replaying = false;

    /**
     * Replay-aware logger that only outputs when not replaying cached steps.
     */
    public readonly LoggerInterface $logger;

    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly string $taskId,
        public readonly string $runId,
        public readonly int $attempt,
        public readonly array $headers = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = new ReplayAwareLogger($logger ?? new NullLogger(), $this);
    }

    /**
     * Check if the task is currently replaying cached checkpoints.
     *
     * Returns true until a step is executed that was not previously cached.
     * Use this to conditionally skip side effects (like logging) during replay.
     */
    public function isReplaying(): bool
    {
        return $this->replaying;
    }

    /**
     * @internal
     */
    public function markReplaying(): void
    {
        $this->replaying = true;
    }

    /**
     * @internal
     */
    public function markNotReplaying(): void
    {
        $this->replaying = false;
    }

    /**
     * Execute a named step with automatic checkpointing.
     */
    public function step(string $name, mixed $value): mixed
    {
        return Fiber::suspend(new Checkpoint($name, $value));
    }

    /**
     * Await an event with optional timeout.
     *
     * @throws TimeoutError
     */
    public function awaitEvent(string $eventName, AwaitEventOptions $options = new AwaitEventOptions()): mixed
    {
        return Fiber::suspend(new AwaitEvent($eventName, $options));
    }

    /**
     * Sleep for a duration (in seconds).
     */
    public function sleepFor(string $stepName, float $duration): void
    {
        Fiber::suspend(new SleepFor($stepName, $duration));
    }

    /**
     * Sleep until a specific timestamp.
     */
    public function sleepUntil(string $stepName, DateTimeImmutable $wakeAt): void
    {
        Fiber::suspend(new SleepUntil($stepName, $wakeAt));
    }

    /**
     * Emit an event.
     */
    public function emitEvent(string $eventName, mixed $payload = null): void
    {
        Fiber::suspend(new EmitEvent($eventName, $payload));
    }

    /**
     * Extend the current run's lease.
     *
     * @param int|null $seconds Number of seconds to extend the lease by. If null, uses the original claim timeout.
     */
    public function heartbeat(?int $seconds = null): void
    {
        Fiber::suspend(new Heartbeat($seconds));
    }
}
