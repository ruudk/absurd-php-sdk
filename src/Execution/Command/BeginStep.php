<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;
use Ruudk\Absurd\Execution\StepHandle;

/**
 * @internal
 */
final readonly class BeginStep implements Command
{
    public function __construct(
        public string $name,
    ) {}

    public function execute(Runner $runner): StepHandle
    {
        return $runner->executeBeginStep($this->name);
    }
}
