<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Task\RetryOptions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a task is retried.
 *
 * Listeners can modify the retry options
 */
final class BeforeRetryEvent extends Event
{
    public function __construct(
        public readonly string $taskId,
        public RetryOptions $options,
    ) {}
}
