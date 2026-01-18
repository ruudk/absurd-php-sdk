<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use JsonSerializable;

/**
 * Cancellation policy for task execution.
 */
final readonly class CancellationPolicy implements JsonSerializable
{
    public function __construct(
        public ?int $maxDuration = null,
        public ?int $maxDelay = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->maxDuration !== null) {
            $data['max_duration'] = $this->maxDuration;
        }
        if ($this->maxDelay !== null) {
            $data['max_delay'] = $this->maxDelay;
        }

        return $data;
    }
}
