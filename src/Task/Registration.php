<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use Closure;

/**
 * Registration information for a task handler.
 *
 * @internal
 */
final readonly class Registration
{
    /**
     * @param class-string|null $payloadType
     */
    public function __construct(
        public string $name,
        public string $queue,
        public Closure $handler,
        public ?string $payloadType = null,
        public ?int $defaultMaxAttempts = null,
        public ?CancellationPolicy $defaultCancellation = null,
    ) {}
}
