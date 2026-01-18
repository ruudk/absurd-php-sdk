<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use PDO;
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
        private PDO $pdo,
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

        $stmt = $this->pdo->prepare(
            'SELECT task_id, run_id, attempt, created FROM absurd.spawn_task(:queue, :task_name, :params, :options)',
        );

        if ($stmt === false) {
            throw new TaskExecutionError('Failed to prepare spawn task query');
        }

        $stmt->execute([
            'queue' => $effectiveQueue,
            'task_name' => $taskName,
            'params' => $this->serializer->encode($params),
            'options' => $this->serializer->encode($effectiveOptions),
        ]);

        /** @var array{task_id: string, run_id: string, attempt: int, created: bool}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new TaskExecutionError('Failed to spawn task');
        }

        return new SpawnResult(
            taskId: $row['task_id'],
            runId: $row['run_id'],
            attempt: $row['attempt'],
            created: $row['created'],
        );
    }
}
