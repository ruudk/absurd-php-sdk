<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use JsonSerializable;

/**
 * Options for retrying a task.
 */
final readonly class RetryOptions implements JsonSerializable
{
    public function __construct(
        public ?int $maxAttempts = null,
        public ?bool $spawnNewTask = null,
    ) {}

    public function with(?int $maxAttempts = null, ?bool $spawnNewTask = null): self
    {
        return new self(
            maxAttempts: $maxAttempts ?? $this->maxAttempts,
            spawnNewTask: $spawnNewTask ?? $this->spawnNewTask,
        );
    }

    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->maxAttempts !== null) {
            $data['max_attempts'] = $this->maxAttempts;
        }
        if ($this->spawnNewTask !== null) {
            $data['spawn_new'] = $this->spawnNewTask;
        }

        return $data;
    }
}
