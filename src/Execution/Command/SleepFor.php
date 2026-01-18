<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;

/**
 * Command to sleep for a duration (in seconds).
 *
 * @internal
 */
final readonly class SleepFor implements Command
{
    public function __construct(
        public string $stepName,
        public float $duration,
    ) {}

    public function execute(Runner $runner): null
    {
        $runner->executeSleepFor($this->stepName, $this->duration);

        return null;
    }
}
