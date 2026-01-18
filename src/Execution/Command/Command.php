<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;

/**
 * Base command interface for Fiber-based task execution.
 *
 * @internal
 */
interface Command
{
    public function execute(Runner $runner): mixed;
}
