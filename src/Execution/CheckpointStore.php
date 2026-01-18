<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use PDO;
use Ruudk\Absurd\Task\ClaimedTask;

/**
 * Handles checkpoint storage and retrieval for task execution.
 *
 * @internal
 */
final class CheckpointStore
{
    /** @var array<string, int> */
    private array $stepNameCounter = [];

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        private readonly Context $context,
        private readonly ClaimedTask $task,
    ) {}

    /**
     * Load checkpoints from the database.
     */
    public function load(): void
    {
        $stmt = $this->context->pdo->prepare(
            'SELECT checkpoint_name, state FROM absurd.get_task_checkpoint_states(:queue, :task_id, :run_id)',
        );

        if ($stmt === false) {
            return;
        }

        $stmt->execute([
            'queue' => $this->context->queueName,
            'task_id' => $this->task->taskId,
            'run_id' => $this->task->runId,
        ]);

        /** @var list<array{checkpoint_name: string, state: string}>|false $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return;
        }

        foreach ($rows as $row) {
            $this->cache[$row['checkpoint_name']] = $this->context->serializer->decode($row['state']);
        }
    }

    /**
     * Check if a checkpoint exists.
     */
    public function has(string $name): bool
    {
        return array_key_exists($this->resolveName($name, false), $this->cache);
    }

    /**
     * Get a checkpoint value without incrementing the counter.
     */
    public function get(string $name): mixed
    {
        return $this->cache[$this->resolveName($name, false)] ?? null;
    }

    /**
     * Get a checkpoint value, incrementing the name counter.
     */
    public function getAndAdvance(string $name): mixed
    {
        $resolved = $this->resolveName($name, true);
        return $this->cache[$resolved] ?? null;
    }

    /**
     * Check if checkpoint exists and get value, incrementing the counter.
     */
    public function checkAndAdvance(string $name): CheckpointResult
    {
        $resolved = $this->resolveName($name, true);
        $exists = array_key_exists($resolved, $this->cache);
        return new CheckpointResult(exists: $exists, value: $exists ? $this->cache[$resolved] : null, name: $resolved);
    }

    /**
     * Persist a checkpoint value.
     */
    public function persist(string $resolvedName, mixed $value): void
    {
        $stmt = $this->context->pdo->prepare(
            'SELECT absurd.set_task_checkpoint_state(:queue, :task_id, :checkpoint_name, :state, :run_id, :claim_timeout)',
        );
        if ($stmt !== false) {
            $stmt->execute([
                'queue' => $this->context->queueName,
                'task_id' => $this->task->taskId,
                'checkpoint_name' => $resolvedName,
                'state' => $this->context->serializer->encode($value),
                'run_id' => $this->task->runId,
                'claim_timeout' => $this->context->claimTimeout,
            ]);
        }
        $this->cache[$resolvedName] = $value;
    }

    /**
     * Resolve a checkpoint name with automatic deduplication.
     */
    private function resolveName(string $name, bool $advance): string
    {
        if ($advance) {
            $count = ($this->stepNameCounter[$name] ?? 0) + 1;
            $this->stepNameCounter[$name] = $count;
            return $count === 1 ? $name : sprintf('%s#%d', $name, $count);
        }

        $count = $this->stepNameCounter[$name] ?? 0;
        return $count === 1 ? $name : sprintf('%s#%d', $name, $count);
    }
}
