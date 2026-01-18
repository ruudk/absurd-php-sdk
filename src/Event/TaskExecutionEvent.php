<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Closure;
use Ruudk\Absurd\Task\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a task is about to be executed.
 *
 * Listeners can wrap the execution by providing a wrapper closure.
 * The wrapper receives the original execute closure and must call it.
 *
 * Example usage for context propagation:
 *
 *     $dispatcher->addListener(TaskExecutionEvent::class, function (TaskExecutionEvent $event) {
 *         $event->wrapExecution(function (Closure $execute) use ($event) {
 *             $scope = TraceContext::restore($event->context->headers['trace_id'] ?? null);
 *             try {
 *                 return $execute();
 *             } finally {
 *                 $scope->detach();
 *             }
 *         });
 *     });
 */
final class TaskExecutionEvent extends Event
{
    /** @var (Closure(Closure(): mixed): mixed)|null */
    private ?Closure $wrapper = null;

    public function __construct(
        public readonly Context $context,
    ) {}

    /**
     * Set a wrapper around the task execution.
     *
     * @param Closure(Closure(): mixed): mixed $wrapper
     */
    public function wrapExecution(Closure $wrapper): void
    {
        $this->wrapper = $wrapper;
    }

    /**
     * @return (Closure(Closure(): mixed): mixed)|null
     */
    public function getWrapper(): ?Closure
    {
        return $this->wrapper;
    }
}
