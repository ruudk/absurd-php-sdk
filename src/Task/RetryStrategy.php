<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

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

    public static function exponential(int $baseSeconds = 10, float $factor = 2.0, int $maxSeconds = 300): self
    {
        return new self('exponential', $baseSeconds, $factor, $maxSeconds);
    }

    public static function linear(int $baseSeconds = 10, int $maxSeconds = 300): self
    {
        return new self('linear', $baseSeconds, null, $maxSeconds);
    }

    public static function fixed(int $seconds): self
    {
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
