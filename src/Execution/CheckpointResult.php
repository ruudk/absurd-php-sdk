<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

/**
 * Result of checking a checkpoint.
 *
 * @internal
 */
final readonly class CheckpointResult
{
    public function __construct(
        public bool $exists,
        public mixed $value,
        public string $name,
    ) {}
}
