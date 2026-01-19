<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

/**
 * Result of executing a checkpoint step.
 *
 * @internal
 */
final readonly class StepResult
{
    public function __construct(
        public mixed $value,
        public bool $wasReplayed,
    ) {}
}
