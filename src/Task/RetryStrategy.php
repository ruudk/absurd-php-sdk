<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Retry strategy configuration for task execution.
 */
final readonly class RetryStrategy implements JsonSerializable
{
    public function __construct(
        public string $kind,
        public ?int $baseSeconds = null,
        public ?float $factor = null,
        public ?int $maxSeconds = null,
    ) {}

    /**
     * @throws InvalidArgumentException If baseSeconds <= 0 or factor <= 1
     */
    public static function exponential(int $baseSeconds = 10, float $factor = 2.0, int $maxSeconds = 300): self
    {
        if ($baseSeconds <= 0) {
            throw new InvalidArgumentException('baseSeconds must be greater than 0');
        }
        if ($factor <= 1.0) {
            throw new InvalidArgumentException('factor must be greater than 1');
        }

        return new self('exponential', $baseSeconds, $factor, $maxSeconds);
    }

    /**
     * @throws InvalidArgumentException If baseSeconds <= 0
     */
    public static function linear(int $baseSeconds = 10, int $maxSeconds = 300): self
    {
        if ($baseSeconds <= 0) {
            throw new InvalidArgumentException('baseSeconds must be greater than 0');
        }

        return new self('linear', $baseSeconds, null, $maxSeconds);
    }

    /**
     * @throws InvalidArgumentException If seconds <= 0
     */
    public static function fixed(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('seconds must be greater than 0');
        }

        return new self('fixed', $seconds);
    }

    /**
     * No delay between retries - immediate requeue.
     */
    public static function none(): self
    {
        return new self('none');
    }

    public function jsonSerialize(): array
    {
        $data = ['kind' => $this->kind];

        if ($this->baseSeconds !== null) {
            $data['base_seconds'] = $this->baseSeconds;
        }
        if ($this->factor !== null) {
            $data['factor'] = $this->factor;
        }
        if ($this->maxSeconds !== null) {
            $data['max_seconds'] = $this->maxSeconds;
        }

        return $data;
    }
}
