<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use JsonSerializable;

/**
 * Options for spawning a task.
 */
final readonly class SpawnOptions implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $headers
     */
    public function __construct(
        public ?int $maxAttempts = null,
        public ?RetryStrategy $retryStrategy = null,
        public ?CancellationPolicy $cancellation = null,
        public ?array $headers = null,
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * Create a new instance with modified properties (immutable modification).
     *
     * @param array<string, mixed>|null $headers
     */
    public function with(
        ?int $maxAttempts = null,
        ?RetryStrategy $retryStrategy = null,
        ?CancellationPolicy $cancellation = null,
        ?array $headers = null,
        ?string $idempotencyKey = null,
    ): self {
        return new self(
            maxAttempts: $maxAttempts ?? $this->maxAttempts,
            retryStrategy: $retryStrategy ?? $this->retryStrategy,
            cancellation: $cancellation ?? $this->cancellation,
            headers: $headers ?? $this->headers,
            idempotencyKey: $idempotencyKey ?? $this->idempotencyKey,
        );
    }

    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->maxAttempts !== null) {
            $data['max_attempts'] = $this->maxAttempts;
        }
        if ($this->retryStrategy !== null) {
            $data['retry_strategy'] = $this->retryStrategy->jsonSerialize();
        }
        if ($this->cancellation !== null) {
            $cancellationData = $this->cancellation->jsonSerialize();
            if ($cancellationData !== []) {
                $data['cancellation'] = $cancellationData;
            }
        }
        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }
        if ($this->idempotencyKey !== null) {
            $data['idempotency_key'] = $this->idempotencyKey;
        }

        return $data;
    }
}
