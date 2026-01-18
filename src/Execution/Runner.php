<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use DateTimeImmutable;
use PDO;
use Ruudk\Absurd\Exception\SuspendTask;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Exception\TimeoutError;
use Ruudk\Absurd\Task\ClaimedTask;
use Throwable;

/**
 * Internal class that handles task execution state and operations.
 *
 * @internal
 */
final readonly class Runner
{
    private function __construct(
        private Context $context,
        private string $taskId,
        private ClaimedTask $task,
        private CheckpointStore $checkpoints,
    ) {}

    /**
     * Create a Runner by loading checkpoints from the database.
     */
    public static function create(Context $context, string $taskId, ClaimedTask $task): self
    {
        $checkpoints = new CheckpointStore($context, $task);
        $checkpoints->load();
        return new self($context, $taskId, $task, $checkpoints);
    }

    /**
     * Execute a named step with checkpointing.
     */
    public function executeCheckpoint(string $name, mixed $value): mixed
    {
        $checkpoint = $this->checkpoints->checkAndAdvance($name);

        if ($checkpoint->exists) {
            return $checkpoint->value;
        }

        /** @var mixed $result Checkpoint values are dynamically typed */
        $result = is_callable($value) ? $value() : $value;
        $this->checkpoints->persist($checkpoint->name, $result);
        return $result;
    }

    /**
     * Sleep for a duration (in seconds).
     *
     * @throws SuspendTask
     */
    public function executeSleepFor(string $stepName, float $duration): void
    {
        $wakeAt = new DateTimeImmutable(sprintf('+%s seconds', $duration));
        $this->executeSleepUntil($stepName, $wakeAt);
    }

    /**
     * Sleep until a specific timestamp.
     *
     * @throws SuspendTask
     */
    public function executeSleepUntil(string $stepName, DateTimeImmutable $wakeAt): void
    {
        $checkpoint = $this->checkpoints->checkAndAdvance($stepName);

        $actualWakeAt = $checkpoint->exists ? new DateTimeImmutable((string) $checkpoint->value) : $wakeAt;

        if (!$checkpoint->exists) {
            $this->checkpoints->persist($checkpoint->name, $wakeAt->format(DateTimeImmutable::ATOM));
        }

        if (time() < $actualWakeAt->getTimestamp()) {
            $this->scheduleRun($actualWakeAt);
            throw new SuspendTask();
        }
    }

    /**
     * Await an event with optional timeout.
     *
     * @throws SuspendTask|TimeoutError
     */
    public function executeAwaitEvent(string $eventName, AwaitEventOptions $options = new AwaitEventOptions()): mixed
    {
        $timeout = $options->timeout !== null && $options->timeout >= 0 ? $options->timeout : null;
        $stepName = $options->stepName ?? sprintf('$awaitEvent:%s', $eventName);
        $checkpoint = $this->checkpoints->checkAndAdvance($stepName);

        if ($checkpoint->exists) {
            return $checkpoint->value;
        }

        if ($this->task->wakeEvent === $eventName && $this->task->eventPayload === null) {
            $this->task->wakeEvent = null;
            throw new TimeoutError($eventName);
        }

        $stmt = $this->context->pdo->prepare(
            'SELECT should_suspend, payload FROM absurd.await_event(:queue, :task_id, :run_id, :checkpoint_name, :event_name, :timeout)',
        );

        if ($stmt === false) {
            throw new TaskExecutionError('Failed to prepare await event query');
        }

        $stmt->execute([
            'queue' => $this->context->queueName,
            'task_id' => $this->task->taskId,
            'run_id' => $this->task->runId,
            'checkpoint_name' => $checkpoint->name,
            'event_name' => $eventName,
            'timeout' => $timeout,
        ]);

        /** @var array{should_suspend: string, payload: string|null}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new TaskExecutionError('Failed to await event');
        }

        if ($row['should_suspend'] === 'f') {
            /** @var mixed $payload Event payload is dynamically typed */
            $payload = $this->context->serializer->decode($row['payload'] ?? '');
            $this->checkpoints->persist($checkpoint->name, $payload);
            $this->task->eventPayload = null;
            return $payload;
        }

        throw new SuspendTask();
    }

    /**
     * Emit an event.
     */
    public function executeEmitEvent(string $eventName, mixed $payload = null): void
    {
        if ($eventName === '') {
            throw new TaskExecutionError('eventName must be a non-empty string');
        }

        $this->executeQuery('SELECT absurd.emit_event(:queue, :event, :payload)', [
            'queue' => $this->context->queueName,
            'event' => $eventName,
            'payload' => $this->context->serializer->encode($payload),
        ]);
    }

    /**
     * Extend the current run's lease.
     */
    public function executeHeartbeat(?int $seconds = null): void
    {
        $timeout = $seconds ?? $this->context->claimTimeout;

        $this->executeQuery('SELECT absurd.extend_claim(:queue, :run_id, :seconds)', [
            'queue' => $this->context->queueName,
            'run_id' => $this->task->runId,
            'seconds' => $timeout,
        ]);
    }

    /**
     * Complete the current run with a result.
     */
    public function complete(mixed $result): void
    {
        $this->executeQuery('SELECT absurd.complete_run(:queue, :run_id, :result)', [
            'queue' => $this->context->queueName,
            'run_id' => $this->task->runId,
            'result' => $this->context->serializer->encode($result),
        ]);
    }

    /**
     * Fail the current run with an error.
     */
    public function fail(Throwable $error): void
    {
        $this->executeQuery('SELECT absurd.fail_run(:queue, :run_id, :error, :retry_at)', [
            'queue' => $this->context->queueName,
            'run_id' => $this->task->runId,
            'error' => $this->context->serializer->encode([
                'name' => $error::class,
                'message' => $error->getMessage(),
                'stack' => $error->getTraceAsString(),
            ]),
            'retry_at' => null,
        ]);
    }

    private function scheduleRun(DateTimeImmutable $wakeAt): void
    {
        $this->executeQuery('SELECT absurd.schedule_run(:queue, :run_id, :wake_at)', [
            'queue' => $this->context->queueName,
            'run_id' => $this->task->runId,
            'wake_at' => $wakeAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function executeQuery(string $sql, array $params): void
    {
        $stmt = $this->context->pdo->prepare($sql);
        if ($stmt !== false) {
            $stmt->execute($params);
        }
    }
}
