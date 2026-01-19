<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * A logger that only outputs when the task is not replaying cached checkpoints.
 *
 * This prevents duplicate log entries when a task resumes after sleeping or
 * awaiting an event, since the task handler runs from the beginning but
 * cached steps are skipped.
 *
 * Automatically injects taskId and runId into the log context.
 */
final readonly class ReplayAwareLogger implements LoggerInterface
{
    public function __construct(
        private LoggerInterface $inner,
        private Context $context,
    ) {}

    public function emergency(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->emergency($message, $this->enrichContext($context));
        }
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->alert($message, $this->enrichContext($context));
        }
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->critical($message, $this->enrichContext($context));
        }
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->error($message, $this->enrichContext($context));
        }
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->warning($message, $this->enrichContext($context));
        }
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->notice($message, $this->enrichContext($context));
        }
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->info($message, $this->enrichContext($context));
        }
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->debug($message, $this->enrichContext($context));
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!$this->context->isReplaying()) {
            $this->inner->log($level, $message, $this->enrichContext($context));
        }
    }

    /**
     * Enrich log context with task metadata.
     *
     * @param array<array-key, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichContext(array $context): array
    {
        /** @var array<string, mixed> */
        return [
            'taskId' => $this->context->taskId,
            'runId' => $this->context->runId,
            ...$context,
        ];
    }
}
