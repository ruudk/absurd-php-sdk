<?php declare(strict_types=1);

namespace Ruudk\Absurd\Worker;

use PDOException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Event\TaskErrorEvent;
use Ruudk\Absurd\Task\ClaimedTask;
use Ruudk\Absurd\Task\ClaimOptions;
use Throwable;

/**
 * Worker that continuously polls for and processes tasks.
 */
final class Worker
{
    private bool $running = false;

    /**
     * @internal
     */
    public function __construct(
        private readonly Absurd $absurd,
        private readonly WorkerOptions $options,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * Start the worker loop.
     */
    public function start(): void
    {
        $this->running = true;
        $lastPoll = 0.0;

        $this->options->logger->info('Worker started', ['workerId' => $this->options->workerId]);

        while ($this->running) {
            try {
                // Respect poll interval
                $now = microtime(true);
                $timeSinceLastPoll = $now - $lastPoll;
                if ($timeSinceLastPoll < $this->options->pollInterval) {
                    $this->wait($this->options->pollInterval - $timeSinceLastPoll);
                    continue;
                }

                $lastPoll = $now;

                // Claim and process tasks
                $tasks = $this->absurd->claimTasks(new ClaimOptions(
                    workerId: $this->options->workerId,
                    claimTimeout: $this->options->claimTimeout,
                    batchSize: $this->options->batchSize,
                ));

                if ($tasks === []) {
                    continue;
                }

                $this->options->logger->info('Claimed {count} task(s)', ['count' => count($tasks)]);

                foreach ($tasks as $task) {
                    $this->options->logger->info('Executing task {taskName}', [
                        'taskId' => $task->taskId,
                        'taskName' => $task->taskName,
                        'attempt' => $task->attempt,
                    ]);

                    try {
                        $this->absurd->executeTask(
                            $task,
                            $this->options->claimTimeout,
                            $this->options->fatalOnLeaseTimeout,
                            $this->options->logger,
                        );

                        $this->options->logger->info('Task completed', ['taskId' => $task->taskId]);
                    } catch (Throwable $exception) {
                        $this->options->logger->error('Task failed: {message}', [
                            'taskId' => $task->taskId,
                            'message' => $exception->getMessage(),
                        ]);

                        // Rethrow connection errors, handle other task errors
                        if ($this->isConnectionError($exception)) {
                            throw $exception;
                        }
                        $this->dispatchError($exception, $task);
                    }
                }
            } catch (Throwable $exception) {
                // Connection errors are fatal - stop the worker
                if ($this->isConnectionError($exception)) {
                    $this->dispatchError($exception);
                    throw $exception;
                }
                $this->dispatchError($exception);
                $this->wait($this->options->pollInterval);
            }
        }

        $this->options->logger->info('Worker stopped');
    }

    /**
     * Stop the worker gracefully.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Dispatch an error event.
     */
    private function dispatchError(Throwable $exception, ?ClaimedTask $task = null): void
    {
        $this->eventDispatcher?->dispatch(new TaskErrorEvent($exception, $task));
    }

    /**
     * Wait for a duration (in seconds).
     */
    private function wait(float $seconds): void
    {
        $microseconds = (int) ($seconds * 1_000_000);
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    /**
     * Check if an exception indicates a database connection error.
     */
    private function isConnectionError(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        // SQLSTATE HY000 with driver code 7 = "no connection to the server"
        // SQLSTATE 08xxx = connection exceptions in SQL standard
        $sqlState = $exception->getCode();

        if (is_string($sqlState) && str_starts_with($sqlState, '08')) {
            return true;
        }

        // Check for common connection error messages
        $message = strtolower($exception->getMessage());

        return (
            str_contains($message, 'no connection')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection timed out')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
        );
    }
}
