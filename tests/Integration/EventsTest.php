<?php declare(strict_types=1);

namespace Ruudk\Absurd\Integration;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Connection\PdoConnection;
use Ruudk\Absurd\Event\TaskCompletedEvent;
use Ruudk\Absurd\Event\TaskFailedEvent;
use Ruudk\Absurd\Event\TaskStartedEvent;
use Ruudk\Absurd\Event\WorkerStartedEvent;
use Ruudk\Absurd\Event\WorkerStoppedEvent;
use Ruudk\Absurd\Task\Context;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EventsTest extends IntegrationTestCase
{
    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new RecordingDispatcher();
        $this->absurd = new Absurd(
            new PdoConnection(self::$pdo),
            $this->createSerializer(),
            $this->queueName,
            eventDispatcher: $this->dispatcher,
        );
    }

    #[Test]
    public function taskCompletedEventIsDispatched(): void
    {
        $this->absurd->registerTask('event-task', static fn(array $p, Context $ctx) => 'done');
        $this->absurd->spawn('event-task', []);
        $this->processAllTasks();

        static::assertCount(1, $this->dispatcher->eventsOf(TaskStartedEvent::class));
        static::assertCount(1, $this->dispatcher->eventsOf(TaskCompletedEvent::class));
        static::assertCount(0, $this->dispatcher->eventsOf(TaskFailedEvent::class));

        $completed = $this->dispatcher->eventsOf(TaskCompletedEvent::class)[0];
        static::assertFalse($completed->suspended);
        static::assertSame('done', $completed->result);
    }

    #[Test]
    public function taskFailedEventIsDispatched(): void
    {
        $this->absurd->registerTask(
            'failing-task',
            static fn(array $p, Context $ctx) => throw new \RuntimeException('boom'),
        );
        $this->absurd->spawn('failing-task', []);
        $this->processAllTasks();

        static::assertCount(0, $this->dispatcher->eventsOf(TaskCompletedEvent::class));
        static::assertCount(1, $this->dispatcher->eventsOf(TaskFailedEvent::class));

        $failed = $this->dispatcher->eventsOf(TaskFailedEvent::class)[0];
        static::assertSame('boom', $failed->error->getMessage());
    }

    #[Test]
    public function workerStartedAndStoppedEventsAreDispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $recording = new RecordingDispatcher();
        $dispatcher->addListener(WorkerStartedEvent::class, [$recording, 'dispatch']);
        $dispatcher->addListener(WorkerStoppedEvent::class, [$recording, 'dispatch']);

        $absurd = new Absurd(
            new PdoConnection(self::$pdo),
            $this->createSerializer(),
            $this->queueName,
            eventDispatcher: $dispatcher,
        );
        $worker = $absurd->startWorker();

        $dispatcher->addListener(WorkerStartedEvent::class, $worker->stop(...));

        $worker->start();

        static::assertCount(1, $recording->eventsOf(WorkerStartedEvent::class));
        static::assertCount(1, $recording->eventsOf(WorkerStoppedEvent::class));
    }

    #[Test]
    public function suspendedTaskDispatchesCompletedWithSuspendedFlag(): void
    {
        $this->absurd->registerTask('suspending-task', static function (array $p, Context $ctx) {
            $ctx->awaitEvent('some-event');
            return 'done';
        });
        $this->absurd->spawn('suspending-task', []);
        $this->processAllTasks();

        static::assertCount(1, $this->dispatcher->eventsOf(TaskCompletedEvent::class));

        $completed = $this->dispatcher->eventsOf(TaskCompletedEvent::class)[0];
        static::assertTrue($completed->suspended);
    }
}

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var object[] */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T[]
     */
    public function eventsOf(string $class): array
    {
        return array_values(array_filter($this->events, static fn($e) => $e instanceof $class));
    }
}
