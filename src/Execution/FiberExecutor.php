<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use Closure;
use Fiber;
use Ruudk\Absurd\Exception\SuspendTask;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Execution\Command\Command;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Worker\LeaseMonitor;

/**
 * Executes task handlers using Fibers.
 *
 * @internal
 */
final readonly class FiberExecutor
{
    public function __construct(
        private Runner $runner,
        private LeaseMonitor $leaseMonitor,
    ) {}

    /**
     * Execute a task handler in a Fiber, processing suspended commands.
     *
     * @throws SuspendTask
     */
    public function execute(Closure $handler, mixed $params, TaskContext $ctx): mixed
    {
        $fiber = new Fiber($handler);

        /** @var Command|null $command Fiber yields Command objects or null when terminated */
        $command = $fiber->start($params, $ctx);

        while (!$fiber->isTerminated()) {
            $this->leaseMonitor->check();

            if (!$command instanceof Command) {
                throw new TaskExecutionError('Fiber suspended with non-Command value: ' . get_debug_type($command));
            }

            /** @var mixed $result Command execution returns dynamic types */
            $result = $command->execute($this->runner);

            /** @var Command|null $command */
            $command = $fiber->resume($result);
        }

        return $fiber->getReturn();
    }
}
