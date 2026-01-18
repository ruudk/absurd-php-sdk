<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

/**
 * Options for registering a task.
 */
final readonly class RegisterOptions
{
    public function __construct(
        public ?string $queue = null,
        public ?int $defaultMaxAttempts = null,
        public ?CancellationPolicy $defaultCancellation = null,
    ) {}
}
