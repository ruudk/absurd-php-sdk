<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use Ruudk\Absurd\Connection\Connection;
use Ruudk\Absurd\Exception\QueryException;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Serialization\Serializer;

/**
 * Handles spawning tasks.
 *
 * @internal
 */
final readonly class Spawner
{
    public function __construct(
        private Connection $connection,
        private Serializer $serializer,
        private int $defaultMaxAttempts,
    ) {}

    public function spawn(
        string $taskName,
        mixed $params,
        SpawnOptions $options,
        ?string $queue,
        ?Registration $registration,
    ): SpawnResult {
        $effectiveQueue = $queue ?? $registration?->queue;

        if ($effectiveQueue === null) {
            throw new TaskExecutionError(sprintf(
                'Task "%s" is not registered. Provide queue when spawning unregistered tasks.',
                $taskName,
            ));
        }

        if ($registration !== null && $queue !== null && $queue !== $registration->queue) {
            throw new TaskExecutionError(sprintf(
                'Task "%s" is registered for queue "%s" but spawn requested queue "%s".',
                $taskName,
                $registration->queue,
                $queue,
            ));
        }

        $effectiveOptions = new SpawnOptions(
            maxAttempts: $options->maxAttempts ?? $registration->defaultMaxAttempts ?? $this->defaultMaxAttempts,
            retryStrategy: $options->retryStrategy,
            cancellation: $options->cancellation ?? $registration?->defaultCancellation,
            headers: $options->headers,
            idempotencyKey: $options->idempotencyKey,
        );

        try {
            /** @var array{task_id: string, run_id: string, attempt: int, created: bool}|false $row */
            $row = $this->connection->fetch('SELECT task_id, run_id, attempt, created FROM absurd.spawn_task(:queue, :task_name, :params, :options)', [
                'queue' => $effectiveQueue,
                'task_name' => $taskName,
                'params' => $this->serializer->encode($params),
                'options' => json_encode($effectiveOptions, JSON_THROW_ON_ERROR),
            ]);
        } catch (QueryException $e) {
            throw new TaskExecutionError('Failed to spawn task', $e);
        }

        return new SpawnResult(
            taskId: $row['task_id'],
            runId: $row['run_id'],
            attempt: $row['attempt'],
            created: $row['created'],
        );
    }

    public function retry(string $taskId, string $queue, RetryOptions $options = new RetryOptions()): SpawnResult
    {
        try {
            /** @var array{task_id: string, run_id: string, attempt: int, created: bool}|false $row */
            $row = $this->connection->fetch('SELECT task_id, run_id, attempt, created FROM absurd.retry_task(:queue, :task_id, :retry_options)', [
                'queue' => $queue,
                'task_id' => $taskId,
                'retry_options' => json_encode($options->jsonSerialize()),
            ]);
        } catch (QueryException $e) {
            throw new TaskExecutionError('Failed to retry task', $e);
        }

        return new SpawnResult(
            taskId: $row['task_id'],
            runId: $row['run_id'],
            attempt: $row['attempt'],
            created: $row['created'],
        );
    }
}
