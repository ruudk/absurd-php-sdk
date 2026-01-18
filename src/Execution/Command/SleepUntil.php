<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use DateTimeImmutable;
use Ruudk\Absurd\Execution\Runner;

/**
 * Command to sleep until a specific timestamp.
 *
 * @internal
 */
final readonly class SleepUntil implements Command
{
    public function __construct(
        public string $stepName,
        public DateTimeImmutable $wakeAt,
    ) {}

    public function execute(Runner $runner): null
    {
        $runner->executeSleepUntil($this->stepName, $this->wakeAt);

        return null;
    }
}
