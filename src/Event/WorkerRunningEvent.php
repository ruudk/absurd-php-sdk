<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Worker\Worker;

final readonly class WorkerRunningEvent
{
    public function __construct(
        public Worker $worker,
        public string $queueName,
        public int $tasksHandled,
    ) {}

    public function isIdle(): bool
    {
        return $this->tasksHandled === 0;
    }
}
