<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;
use Ruudk\Absurd\Execution\StepHandle;

/**
 * @internal
 */
final readonly class CompleteStep implements Command
{
    public function __construct(
        public StepHandle $handle,
        public mixed $result,
    ) {}

    public function execute(Runner $runner): mixed
    {
        $runner->executeCompleteStep($this->handle->checkpointName, $this->result);
        return $this->result;
    }
}
