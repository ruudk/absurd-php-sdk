<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

final readonly class StepHandle
{
    private function __construct(
        public string $name,
        public string $checkpointName,
        public bool $done,
        private mixed $state = null,
    ) {}

    public static function pending(string $name, string $checkpointName): self
    {
        return new self($name, $checkpointName, false);
    }

    public static function completed(string $name, string $checkpointName, mixed $state): self
    {
        return new self($name, $checkpointName, true, $state);
    }

    public function state(): mixed
    {
        if (!$this->done) {
            throw new \LogicException('Step is not completed yet');
        }
        return $this->state;
    }
}
