<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use Ruudk\Absurd\Exception\QueryException;
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
        try {
            $rows = $this->context->connection->fetchAll('SELECT checkpoint_name, state FROM absurd.get_task_checkpoint_states(:queue, :task_id, :run_id)', [
                'queue' => $this->context->queueName,
                'task_id' => $this->task->taskId,
                'run_id' => $this->task->runId,
            ]);

            foreach ($rows as $row) {
                $this->cache[(string) $row['checkpoint_name']] = $this->context->serializer->decode(
                    (string) $row['state'],
                );
            }
        } catch (QueryException) {
            return;
        }
    }

    /**
     * Check if any checkpoints have been loaded.
     */
    public function hasAny(): bool
    {
        return $this->cache !== [];
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
        $this->context->connection->execute('SELECT absurd.set_task_checkpoint_state(:queue, :task_id, :checkpoint_name, :state, :run_id, :claim_timeout)', [
            'queue' => $this->context->queueName,
            'task_id' => $this->task->taskId,
            'checkpoint_name' => $resolvedName,
            'state' => $this->context->serializer->encode($value),
            'run_id' => $this->task->runId,
            'claim_timeout' => $this->context->claimTimeout,
        ]);

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
