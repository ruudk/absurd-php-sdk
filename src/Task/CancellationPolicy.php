<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Cancellation policy for task execution.
 */
final readonly class CancellationPolicy implements JsonSerializable
{
    /**
     * @throws InvalidArgumentException If maxDuration or maxDelay are invalid
     */
    public function __construct(
        public ?int $maxDuration = null,
        public ?int $maxDelay = null,
    ) {
        if ($maxDuration !== null && $maxDuration <= 0) {
            throw new InvalidArgumentException('maxDuration must be greater than 0');
        }
        if ($maxDelay !== null && $maxDelay <= 0) {
            throw new InvalidArgumentException('maxDelay must be greater than 0');
        }
    }

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
