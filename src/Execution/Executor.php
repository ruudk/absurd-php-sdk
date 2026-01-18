<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ruudk\Absurd\Event\TaskExecutionEvent;
use Ruudk\Absurd\Exception\SuspendTask;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Task\ClaimedTask;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Task\Registration;
use Ruudk\Absurd\Worker\LeaseMonitor;
use Throwable;

/**
 * Handles executing a single task.
 *
 * @internal
 */
final readonly class Executor
{
    /**
     * @param array<string, Registration> $registry
     */
    public function __construct(
        private Context $context,
        private array $registry,
        private LoggerInterface $logger = new NullLogger(),
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function execute(ClaimedTask $task, int $claimTimeout, bool $fatalOnLeaseTimeout): void
    {
        $registration = $this->registry[$task->taskName] ?? null;

        if ($registration === null) {
            throw new TaskExecutionError(sprintf('Unknown task: %s', $task->taskName));
        }

        if ($registration->queue !== $this->context->queueName) {
            throw new TaskExecutionError('Misconfigured task (queue mismatch)');
        }

        $runner = Runner::create($this->context, $task->taskId, $task);
        $fiberExecutor = new FiberExecutor(
            $runner,
            new LeaseMonitor($task, $claimTimeout, $fatalOnLeaseTimeout, $this->logger),
        );

        try {
            /** @var mixed $params Task parameters are dynamically typed based on handler signature */
            $params = $this->context->serializer->decode($task->rawParams, $registration->payloadType);
            $ctx = new TaskContext($task->taskId, $task->runId, $task->attempt, $task->headers ?? []);

            $execute = static fn(): mixed => $fiberExecutor->execute($registration->handler, $params, $ctx);

            $wrapper = null;
            if ($this->eventDispatcher !== null) {
                $event = new TaskExecutionEvent($ctx);
                $this->eventDispatcher->dispatch($event);
                $wrapper = $event->getWrapper();
            }

            /** @var mixed $result Task handlers return dynamic types */
            $result = $wrapper !== null ? $wrapper($execute) : $execute();

            $runner->complete($result);
        } catch (SuspendTask) {
            return;
        } catch (Throwable $exception) {
            $runner->fail($exception);
        }
    }
}
