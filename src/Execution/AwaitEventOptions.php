<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

/**
 * Options for awaiting an event.
 */
final readonly class AwaitEventOptions
{
    public function __construct(
        public ?string $stepName = null,
        public ?int $timeout = null,
    ) {}
}
