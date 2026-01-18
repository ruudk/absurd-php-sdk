<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use DateTimeImmutable;
use Fiber;
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
 */
final class Context
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly string $taskId,
        public readonly string $runId,
        public readonly int $attempt,
        public readonly array $headers = [],
    ) {}

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
