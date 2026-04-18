<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Event;

final readonly class WorkerStoppedEvent
{
    public function __construct(
        public string $queueName,
        public string $workerId,
    ) {}
}
