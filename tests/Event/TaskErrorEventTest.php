<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Task\ClaimedTask;

final class TaskErrorEventTest extends TestCase
{
    #[Test]
    public function constructsWithExceptionOnly(): void
    {
        $exception = new Exception('Something went wrong');
        $event = new TaskErrorEvent($exception);

        static::assertSame($exception, $event->exception);
        static::assertNull($event->task);
    }

    #[Test]
    public function constructsWithExceptionAndTask(): void
    {
        $exception = new Exception('Task failed');
        $task = new ClaimedTask(
            runId: 'run-123',
            taskId: 'task-456',
            attempt: 2,
            taskName: 'my-task',
            rawParams: '{}',
            retryStrategy: null,
            maxAttempts: 5,
            headers: ['trace_id' => 'abc'],
            wakeEvent: null,
            eventPayload: null,
        );

        $event = new TaskErrorEvent($exception, $task);

        static::assertSame($exception, $event->exception);
        static::assertSame($task, $event->task);
    }
}
