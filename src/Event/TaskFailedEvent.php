<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Task\ClaimedTask;

final readonly class TaskFailedEvent
{
    public function __construct(
        public ClaimedTask $task,
        public string $queueName,
        public ?\Throwable $error,
    ) {}
}
