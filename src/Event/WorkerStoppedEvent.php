<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Worker\Worker;

final readonly class WorkerStoppedEvent
{
    public function __construct(
        public Worker $worker,
        public string $queueName,
    ) {}
}
