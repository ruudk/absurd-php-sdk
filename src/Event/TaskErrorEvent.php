<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Task\ClaimedTask;
use Throwable;

/**
 * Dispatched when a task execution fails with an error.
 *
 * Listeners can use this event for logging, monitoring, or custom error handling.
 */
final class TaskErrorEvent
{
    public function __construct(
        public readonly Throwable $exception,
        public readonly ?ClaimedTask $task = null,
    ) {}
}
