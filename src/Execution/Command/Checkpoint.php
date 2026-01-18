<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;

/**
 * Command to execute and checkpoint an operation with a named step.
 *
 * @internal
 */
final readonly class Checkpoint implements Command
{
    public function __construct(
        public string $name,
        public mixed $value,
    ) {}

    public function execute(Runner $runner): mixed
    {
        return $runner->executeCheckpoint($this->name, $this->value);
    }
}
