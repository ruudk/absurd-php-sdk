<?php declare(strict_types=1);

namespace Ruudk\Absurd\Worker;

use Psr\Log\LoggerInterface;
use Ruudk\Absurd\Task\ClaimedTask;

/**
 * Monitors task lease timeouts and handles warnings/fatal errors.
 *
 * @internal
 */
final class LeaseMonitor
{
    private ?float $warnTime;
    private ?float $fatalTime;
    private bool $warned = false;

    public function __construct(
        private readonly ClaimedTask $task,
        private readonly int $claimTimeout,
        bool $fatalOnLeaseTimeout,
        private readonly LoggerInterface $logger,
    ) {
        $this->warnTime = $claimTimeout > 0 ? microtime(true) + $claimTimeout : null;
        $this->fatalTime = $claimTimeout > 0 && $fatalOnLeaseTimeout ? microtime(true) + ($claimTimeout * 2) : null;
    }

    /**
     * Check lease timeout and log warnings or terminate if exceeded.
     */
    public function check(): void
    {
        if ($this->warnTime !== null && !$this->warned && microtime(true) > $this->warnTime) {
            $this->logger->warning('Task {task_name} ({task_id}) exceeded claim timeout of {timeout}s', [
                'task_name' => $this->task->taskName,
                'task_id' => $this->task->taskId,
                'timeout' => $this->claimTimeout,
            ]);
            $this->warned = true;
        }

        if ($this->fatalTime !== null && microtime(true) > $this->fatalTime) {
            $this->logger->critical('Task {task_name} ({task_id}) exceeded claim timeout by 100%; terminating process', [
                'task_name' => $this->task->taskName,
                'task_id' => $this->task->taskId,
            ]);
            exit(1);
        }
    }
}
