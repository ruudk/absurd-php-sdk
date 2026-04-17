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
        private int $claimTimeout,
        private readonly bool $fatalOnLeaseTimeout,
        private readonly LoggerInterface $logger,
    ) {
        $this->reset();
    }

    public function arm(): void
    {
        $this->reset();

        if (extension_loaded('pcntl') && $this->claimTimeout > 0) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, $this->check(...));
            pcntl_alarm($this->claimTimeout);
        }
    }

    public function disarm(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_IGN);
        }
    }

    public function reset(?int $seconds = null): void
    {
        if ($seconds) {
            $this->claimTimeout = $seconds;
        }
        $this->warned = false;
        $this->warnTime = $this->claimTimeout > 0 ? microtime(true) + $this->claimTimeout : null;
        $this->fatalTime = $this->claimTimeout > 0 && $this->fatalOnLeaseTimeout ? microtime(true) + ($this->claimTimeout * 2) : null;
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

            if ($this->fatalOnLeaseTimeout && extension_loaded('pcntl')) {
                pcntl_alarm($this->claimTimeout);
            }
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
