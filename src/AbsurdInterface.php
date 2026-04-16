<?php

declare(strict_types=1);

namespace Ruudk\Absurd;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Task\ClaimedTask;
use Ruudk\Absurd\Task\ClaimOptions;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Task\RetryOptions;
use Ruudk\Absurd\Task\SpawnOptions;
use Ruudk\Absurd\Task\SpawnResult;
use Ruudk\Absurd\Task\TaskInfo;
use Ruudk\Absurd\Worker\Worker;
use Ruudk\Absurd\Worker\WorkerOptions;

interface AbsurdInterface
{
    /**
     * @param callable(mixed, TaskContext): mixed $handler
     */
    public function registerTask(
        string $name,
        callable $handler,
        RegisterOptions $options = new RegisterOptions(),
    ): void;

    public function spawn(
        string $taskName,
        mixed $params,
        SpawnOptions $options = new SpawnOptions(),
        ?string $queue = null,
    ): SpawnResult;

    public function retryTask(
        string $taskId,
        RetryOptions $options = new RetryOptions(),
        ?string $queue = null,
    ): SpawnResult;

    public function emitEvent(string $eventName, mixed $payload = null, ?string $queueName = null): void;

    /**
     * Cancel a task by its ID.
     *
     * Running tasks will stop at their next checkpoint, heartbeat, or await event.
     * This operation is idempotent - cancelling an already cancelled task has no effect.
     *
     * @throws TaskExecutionError If taskId is empty
     */
    public function cancelTask(string $taskId, ?string $queueName = null): void;

    /**
     * @return list<ClaimedTask>
     */
    public function claimTasks(ClaimOptions $options = new ClaimOptions()): array;

    public function startWorker(WorkerOptions $options = new WorkerOptions()): Worker;

    public function createQueue(?string $queueName = null): void;

    /**
     * Drop a queue and all its internal tables.
     */
    public function dropQueue(?string $queueName = null): void;

    /**
     * List all queue names.
     *
     * @return list<string>
     */
    public function listQueues(): array;

    public function executeTask(
        ClaimedTask $task,
        int $claimTimeout,
        bool $fatalOnLeaseTimeout = true,
        LoggerInterface $logger = new NullLogger(),
    ): void;

    /**
     * Process a batch of tasks synchronously (one-shot processing).
     *
     * Claims and executes tasks immediately, then returns. Useful for testing
     * or when you want to process tasks without running a long-lived worker.
     *
     * @return int Number of tasks processed
     */
    public function workBatch(WorkerOptions $options = new WorkerOptions()): int;

    /**
     * Clean up old completed or failed tasks.
     *
     * Removes tasks that have been completed or failed for longer than the specified TTL.
     *
     * @param int $ttlSeconds Tasks older than this are eligible for cleanup
     * @param int $limit Maximum number of tasks to clean up in one call
     * @return int Number of tasks cleaned up
     */
    public function cleanupTasks(int $ttlSeconds, int $limit = 1000, ?string $queue = null): int;

    /**
     * Clean up old consumed events.
     *
     * Removes events that have been consumed for longer than the specified TTL.
     *
     * @param int $ttlSeconds Events older than this are eligible for cleanup
     * @param int $limit Maximum number of events to clean up in one call
     * @return int Number of events cleaned up
     */
    public function cleanupEvents(int $ttlSeconds, int $limit = 1000, ?string $queue = null): int;

    /**
     * Get task information by ID.
     *
     * @return TaskInfo|null The task info, or null if the task doesn't exist
     */
    public function getTask(string $taskId, ?string $queueName = null): ?TaskInfo;
}
