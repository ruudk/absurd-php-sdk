<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use Closure;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionFunction;
use ReflectionNamedType;
use Ruudk\Absurd\Event\BeforeSpawnEvent;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Execution\Context as ExecutionContext;
use Ruudk\Absurd\Execution\Executor;
use Ruudk\Absurd\Serialization\Serializer;
use Ruudk\Absurd\Task\ClaimedTask;
use Ruudk\Absurd\Task\Claimer;
use Ruudk\Absurd\Task\ClaimOptions;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Task\Registration;
use Ruudk\Absurd\Task\Spawner;
use Ruudk\Absurd\Task\SpawnOptions;
use Ruudk\Absurd\Task\SpawnResult;
use Ruudk\Absurd\Task\TaskInfo;
use Ruudk\Absurd\Worker\Worker;
use Ruudk\Absurd\Worker\WorkerOptions;

final class Absurd
{
    private string $queueName;

    /** @var array<string, Registration> */
    private array $registry = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Serializer $serializer,
        private readonly string $defaultQueueName = 'default',
        private readonly int $defaultMaxAttempts = 5,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->queueName = $this->defaultQueueName;
    }

    /**
     * @param callable(mixed, TaskContext): mixed $handler
     */
    public function registerTask(
        string $name,
        callable $handler,
        RegisterOptions $options = new RegisterOptions(),
    ): void {
        if ($name === '') {
            throw new TaskExecutionError('Task registration requires a non-empty name');
        }

        $queue = $options->queue ?? $this->defaultQueueName;

        if ($queue === '') {
            throw new TaskExecutionError(sprintf(
                'Task "%s" must specify a queue or use a client with a default queue',
                $name,
            ));
        }

        if ($options->defaultMaxAttempts !== null && $options->defaultMaxAttempts < 1) {
            throw new TaskExecutionError('defaultMaxAttempts must be at least 1');
        }

        $this->registry[$name] = new Registration(
            name: $name,
            queue: $queue,
            handler: $handler(...),
            payloadType: $this->detectPayloadType($handler(...)),
            defaultMaxAttempts: $options->defaultMaxAttempts,
            defaultCancellation: $options->defaultCancellation,
        );
    }

    public function spawn(
        string $taskName,
        mixed $params,
        SpawnOptions $options = new SpawnOptions(),
        ?string $queue = null,
    ): SpawnResult {
        $effectiveOptions = $options;

        if ($this->eventDispatcher !== null) {
            $event = new BeforeSpawnEvent($taskName, $params, $effectiveOptions);
            $this->eventDispatcher->dispatch($event);
            $effectiveOptions = $event->options;
        }

        $spawner = new Spawner($this->pdo, $this->serializer, $this->defaultMaxAttempts);
        return $spawner->spawn($taskName, $params, $effectiveOptions, $queue, $this->registry[$taskName] ?? null);
    }

    public function emitEvent(string $eventName, mixed $payload = null, ?string $queueName = null): void
    {
        if ($eventName === '') {
            throw new TaskExecutionError('eventName must be a non-empty string');
        }

        $this->executeQuery('SELECT absurd.emit_event(:queue, :event, :payload)', [
            'queue' => $queueName ?? $this->queueName,
            'event' => $eventName,
            'payload' => $this->serializer->encode($payload),
        ]);
    }

    /**
     * Cancel a task by its ID.
     *
     * Running tasks will stop at their next checkpoint, heartbeat, or await event.
     * This operation is idempotent - cancelling an already cancelled task has no effect.
     *
     * @throws TaskExecutionError If taskId is empty
     */
    public function cancelTask(string $taskId, ?string $queueName = null): void
    {
        if ($taskId === '') {
            throw new TaskExecutionError('taskId must be a non-empty string');
        }

        $this->executeQuery('SELECT absurd.cancel_task(:queue, :task_id)', [
            'queue' => $queueName ?? $this->queueName,
            'task_id' => $taskId,
        ]);
    }

    /**
     * @return list<ClaimedTask>
     */
    public function claimTasks(ClaimOptions $options = new ClaimOptions()): array
    {
        $claimer = new Claimer($this->pdo, $this->queueName, $this->serializer);
        return $claimer->claim($options->workerId, $options->claimTimeout, $options->batchSize);
    }

    public function startWorker(WorkerOptions $options = new WorkerOptions()): Worker
    {
        return new Worker($this, $options, $this->eventDispatcher);
    }

    public function createQueue(?string $queueName = null): void
    {
        $queue = $queueName ?? $this->queueName;
        $this->executeQuery('SELECT absurd.create_queue(:queue)', ['queue' => $queue]);
    }

    /**
     * Drop a queue and all its internal tables.
     */
    public function dropQueue(?string $queueName = null): void
    {
        $queue = $queueName ?? $this->queueName;
        $this->executeQuery('SELECT absurd.drop_queue(:queue)', ['queue' => $queue]);
    }

    /**
     * List all queue names.
     *
     * @return list<string>
     */
    public function listQueues(): array
    {
        $stmt = $this->pdo->prepare('SELECT queue_name FROM absurd.list_queues()');
        if ($stmt === false) {
            return [];
        }
        $stmt->execute();

        /** @var list<array{queue_name: string}> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_values(array_column($rows, 'queue_name'));
    }

    public function executeTask(
        ClaimedTask $task,
        int $claimTimeout,
        bool $fatalOnLeaseTimeout = true,
        LoggerInterface $logger = new NullLogger(),
    ): void {
        $context = new ExecutionContext($this->pdo, $this->queueName, $claimTimeout, $this->serializer);
        $executor = new Executor($context, $this->registry, $logger, $this->eventDispatcher);
        $executor->execute($task, $claimTimeout, $fatalOnLeaseTimeout);
    }

    /**
     * Process a batch of tasks synchronously (one-shot processing).
     *
     * Claims and executes tasks immediately, then returns. Useful for testing
     * or when you want to process tasks without running a long-lived worker.
     *
     * @return int Number of tasks processed
     */
    public function workBatch(WorkerOptions $options = new WorkerOptions()): int
    {
        $tasks = $this->claimTasks(new ClaimOptions(
            workerId: $options->workerId,
            claimTimeout: $options->claimTimeout,
            batchSize: $options->batchSize,
        ));

        foreach ($tasks as $task) {
            $this->executeTask($task, $options->claimTimeout, $options->fatalOnLeaseTimeout, $options->logger);
        }

        return count($tasks);
    }

    /**
     * Clean up old completed or failed tasks.
     *
     * Removes tasks that have been completed or failed for longer than the specified TTL.
     *
     * @param int $ttlSeconds Tasks older than this are eligible for cleanup
     * @param int $limit Maximum number of tasks to clean up in one call
     * @return int Number of tasks cleaned up
     */
    public function cleanupTasks(int $ttlSeconds, int $limit = 1000): int
    {
        $stmt = $this->pdo->prepare('SELECT absurd.cleanup_tasks(:queue, :ttl_seconds, :limit)');
        if ($stmt === false) {
            return 0;
        }
        $stmt->execute([
            'queue' => $this->queueName,
            'ttl_seconds' => $ttlSeconds,
            'limit' => $limit,
        ]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();
        return is_numeric($result) ? (int) $result : 0;
    }

    /**
     * Clean up old consumed events.
     *
     * Removes events that have been consumed for longer than the specified TTL.
     *
     * @param int $ttlSeconds Events older than this are eligible for cleanup
     * @param int $limit Maximum number of events to clean up in one call
     * @return int Number of events cleaned up
     */
    public function cleanupEvents(int $ttlSeconds, int $limit = 1000): int
    {
        $stmt = $this->pdo->prepare('SELECT absurd.cleanup_events(:queue, :ttl_seconds, :limit)');
        if ($stmt === false) {
            return 0;
        }
        $stmt->execute([
            'queue' => $this->queueName,
            'ttl_seconds' => $ttlSeconds,
            'limit' => $limit,
        ]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();
        return is_numeric($result) ? (int) $result : 0;
    }

    /**
     * Get task information by ID.
     *
     * @return TaskInfo|null The task info, or null if the task doesn't exist
     */
    public function getTask(string $taskId, ?string $queueName = null): ?TaskInfo
    {
        if ($taskId === '') {
            throw new TaskExecutionError('taskId must be a non-empty string');
        }

        $queue = $queueName ?? $this->queueName;
        $tableName = 't_' . $queue;

        // Using prepared statement with quoted table name for safety
        // Note: table name is derived from queue name which is controlled internally
        $stmt = $this->pdo->prepare("SELECT task_id, task_name, state, attempts, completed_payload
             FROM absurd.\"{$tableName}\"
             WHERE task_id = :task_id");
        if ($stmt === false) {
            throw new TaskExecutionError('Failed to prepare query for getTask');
        }

        $stmt->execute(['task_id' => $taskId]);

        /** @var array{task_id: string, task_name: string, state: string, attempts: int, completed_payload: string|null}|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        /** @var mixed $completedPayload */
        $completedPayload = $row['completed_payload'] !== null
            ? $this->serializer->decode($row['completed_payload'])
            : null;

        /** @var 'pending'|'running'|'sleeping'|'completed'|'failed'|'cancelled' $state */
        $state = $row['state'];

        return new TaskInfo(
            taskId: $row['task_id'],
            taskName: $row['task_name'],
            state: $state,
            attempts: (int) $row['attempts'],
            completedPayload: $completedPayload,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function executeQuery(string $sql, array $params): void
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt !== false) {
            $stmt->execute($params);
        }
    }

    /**
     * @return class-string|null
     */
    private function detectPayloadType(Closure $handler): ?string
    {
        $reflection = new ReflectionFunction($handler);
        $params = $reflection->getParameters();

        if ($params === []) {
            return null;
        }

        $firstParam = $params[0];
        $type = $firstParam->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }
}
