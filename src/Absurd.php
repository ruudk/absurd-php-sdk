<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionFunction;
use ReflectionNamedType;
use Ruudk\Absurd\Connection\Connection;
use Ruudk\Absurd\Event\BeforeRetryEvent;
use Ruudk\Absurd\Event\BeforeSpawnEvent;
use Ruudk\Absurd\Exception\QueryException;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Execution\Context as ExecutionContext;
use Ruudk\Absurd\Execution\Executor;
use Ruudk\Absurd\Serialization\Serializer;
use Ruudk\Absurd\Task\ClaimedTask;
use Ruudk\Absurd\Task\Claimer;
use Ruudk\Absurd\Task\ClaimOptions;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Task\Registration;
use Ruudk\Absurd\Task\RetryOptions;
use Ruudk\Absurd\Task\Spawner;
use Ruudk\Absurd\Task\SpawnOptions;
use Ruudk\Absurd\Task\SpawnResult;
use Ruudk\Absurd\Task\TaskInfo;
use Ruudk\Absurd\Worker\Worker;
use Ruudk\Absurd\Worker\WorkerOptions;

final class Absurd implements AbsurdInterface
{
    private string $queueName;

    /** @var array<string, Registration> */
    private array $registry = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $serializer,
        private readonly string $defaultQueueName = 'default',
        private readonly int $defaultMaxAttempts = 5,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->queueName = $this->defaultQueueName;
    }

    /**
     * @throws TaskExecutionError
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

    /**
     * @throws TaskExecutionError
     * @throws \JsonException
     */
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

        $spawner = new Spawner($this->connection, $this->serializer, $this->defaultMaxAttempts);
        return $spawner->spawn($taskName, $params, $effectiveOptions, $queue, $this->registry[$taskName] ?? null);
    }

    /**
     * @throws TaskExecutionError
     */
    public function retryTask(
        string $taskId,
        RetryOptions $options = new RetryOptions(),
        ?string $queue = null,
    ): SpawnResult {
        if ($taskId === '') {
            throw new TaskExecutionError('taskId must be a non-empty string');
        }

        $effectiveOptions = $options;

        if ($this->eventDispatcher !== null) {
            $event = new BeforeRetryEvent($taskId, $effectiveOptions);
            $this->eventDispatcher->dispatch($event);
            $effectiveOptions = $event->options;
        }

        $spawner = new Spawner($this->connection, $this->serializer, $this->defaultMaxAttempts);
        return $spawner->retry($taskId, $queue ?? $this->queueName, $effectiveOptions);
    }

    public function emitEvent(string $eventName, mixed $payload = null, ?string $queueName = null): void
    {
        if ($eventName === '') {
            throw new TaskExecutionError('eventName must be a non-empty string');
        }

        $this->connection->execute('SELECT absurd.emit_event(:queue, :event, :payload)', [
            'queue' => $queueName ?? $this->queueName,
            'event' => $eventName,
            'payload' => $this->serializer->encode($payload),
        ]);
    }

    public function cancelTask(string $taskId, ?string $queueName = null): void
    {
        if ($taskId === '') {
            throw new TaskExecutionError('taskId must be a non-empty string');
        }

        $this->connection->execute('SELECT absurd.cancel_task(:queue, :task_id)', [
            'queue' => $queueName ?? $this->queueName,
            'task_id' => $taskId,
        ]);
    }

    public function claimTasks(ClaimOptions $options = new ClaimOptions()): array
    {
        $claimer = new Claimer($this->connection, $this->queueName, $this->serializer);
        return $claimer->claim($options->workerId, $options->claimTimeout, $options->batchSize);
    }

    public function startWorker(WorkerOptions $options = new WorkerOptions()): Worker
    {
        return new Worker($this, $options, $this->eventDispatcher);
    }

    public function createQueue(?string $queueName = null): void
    {
        $queue = $queueName ?? $this->queueName;
        $this->connection->execute('SELECT absurd.create_queue(:queue)', ['queue' => $queue]);
    }

    public function dropQueue(?string $queueName = null): void
    {
        $queue = $queueName ?? $this->queueName;
        $this->connection->execute('SELECT absurd.drop_queue(:queue)', ['queue' => $queue]);
    }

    public function listQueues(): array
    {
        try {
            /** @var list<array{queue_name: string}> $rows */
            $rows = $this->connection->fetchAll('SELECT queue_name FROM absurd.list_queues()');
            return array_values(array_column($rows, 'queue_name'));
        } catch (QueryException) {
            return [];
        }
    }

    public function executeTask(
        ClaimedTask $task,
        int $claimTimeout,
        bool $fatalOnLeaseTimeout = true,
        LoggerInterface $logger = new NullLogger(),
    ): void {
        $context = new ExecutionContext($this->connection, $this->queueName, $claimTimeout, $this->serializer);
        $executor = new Executor($context, $this->registry, $logger, $this->eventDispatcher);
        $executor->execute($task, $claimTimeout, $fatalOnLeaseTimeout);
    }

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

    public function cleanupTasks(int $ttlSeconds, int $limit = 1000, ?string $queue = null): int
    {
        try {
            $result = $this->connection->scalar('SELECT absurd.cleanup_tasks(:queue, :ttl_seconds, :limit)', [
                [
                    'queue' => $queue ?? $this->queueName,
                    'ttl_seconds' => $ttlSeconds,
                    'limit' => $limit,
                ],
            ]);

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function cleanupEvents(int $ttlSeconds, int $limit = 1000, ?string $queue = null): int
    {
        try {
            $result = $this->connection->scalar('SELECT absurd.cleanup_events(:queue, :ttl_seconds, :limit)', [
                [
                    'queue' => $queue ?? $this->queueName,
                    'ttl_seconds' => $ttlSeconds,
                    'limit' => $limit,
                ],
            ]);

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getTask(string $taskId, ?string $queueName = null): ?TaskInfo
    {
        if ($taskId === '') {
            throw new TaskExecutionError('taskId must be a non-empty string');
        }

        $queue = $queueName ?? $this->queueName;
        $tableName = 't_' . $queue;

        // Using prepared statement with quoted table name for safety
        // Note: table name is derived from queue name which is controlled internally
        try {
            /** @var array{task_id: string, task_name: string, state: string, attempts: int, completed_payload: string|null}|false $row */
            $row = $this->connection->fetch(
                "SELECT task_id, task_name, state, attempts, completed_payload
             FROM absurd.\"{$tableName}\"
             WHERE task_id = :task_id",
                [
                    'task_id' => $taskId,
                ],
            );
        } catch (QueryException $e) {
            throw new TaskExecutionError('Failed to execute getTask', $e);
        }

        if (!$row) {
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
