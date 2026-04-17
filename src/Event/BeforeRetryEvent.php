<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Task\RetryOptions;

/**
 * Dispatched before a task is retried.
 *
 * Listeners can modify the retry options
 */
final class BeforeRetryEvent
{
    public function __construct(
        public readonly string $taskId,
        public RetryOptions $options,
    ) {}
}
