<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Ruudk\Absurd\Event\BeforeSpawnEvent;
use Ruudk\Absurd\Event\TaskExecutionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example subscriber that demonstrates trace context propagation.
 *
 * This subscriber:
 * 1. Injects a trace_id header into all spawned tasks
 * 2. Restores the trace context when executing tasks
 */
final class TracingSubscriber implements EventSubscriberInterface
{
    private const HEADER_TRACE_ID = 'trace_id';

    private ?string $currentTraceId = null;

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSpawnEvent::class => 'onBeforeSpawn',
            TaskExecutionEvent::class => 'onTaskExecution',
        ];
    }

    /**
     * Before spawning a task, inject the current trace ID into headers.
     */
    public function onBeforeSpawn(BeforeSpawnEvent $event): void
    {
        $traceId = $this->currentTraceId ?? $this->generateTraceId();
        $headers = $event->options->headers ?? [];
        $headers[self::HEADER_TRACE_ID] = $traceId;

        $event->options = $event->options->with(headers: $headers);
    }

    /**
     * When executing a task, restore the trace context from headers.
     */
    public function onTaskExecution(TaskExecutionEvent $event): void
    {
        $traceId = $event->context->headers[self::HEADER_TRACE_ID] ?? null;

        if (!is_string($traceId)) {
            return;
        }

        $event->wrapExecution(function ($execute) use ($traceId) {
            $previousTraceId = $this->currentTraceId;
            $this->currentTraceId = $traceId;

            try {
                return $execute();
            } finally {
                $this->currentTraceId = $previousTraceId;
            }
        });
    }

    public function getCurrentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /**
     * Set the trace ID for the current context (useful for incoming HTTP requests).
     */
    public function setTraceId(string $traceId): void
    {
        $this->currentTraceId = $traceId;
    }

    private function generateTraceId(): string
    {
        return sprintf('%08x%08x', random_int(0, 0xFFFFFFFF), random_int(0, 0xFFFFFFFF));
    }
}
