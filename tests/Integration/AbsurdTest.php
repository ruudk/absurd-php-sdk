<?php declare(strict_types=1);

namespace Ruudk\Absurd\Integration;

use PHPUnit\Framework\Attributes\Test;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Task\RetryStrategy;
use Ruudk\Absurd\Task\SpawnOptions;

final class AbsurdTest extends IntegrationTestCase
{
    #[Test]
    public function registerTaskWithEmptyNameThrows(): void
    {
        $this->expectException(TaskExecutionError::class);
        $this->expectExceptionMessage('Task registration requires a non-empty name');

        $this->absurd->registerTask('', static fn(array $p, TaskContext $ctx) => yield from []);
    }

    #[Test]
    public function registerTaskWithInvalidMaxAttemptsThrows(): void
    {
        $this->expectException(TaskExecutionError::class);
        $this->expectExceptionMessage('defaultMaxAttempts must be at least 1');

        $this->absurd->registerTask(
            'test-task',
            static fn(array $p, TaskContext $ctx) => yield from [],
            new RegisterOptions(defaultMaxAttempts: 0),
        );
    }

    #[Test]
    public function spawnCreatesTask(): void
    {
        $this->absurd->registerTask('my-task', static fn(array $p, TaskContext $ctx) => yield from []);

        $result = $this->absurd->spawn('my-task', ['foo' => 'bar']);

        static::assertNotEmpty($result->taskId);
        static::assertNotEmpty($result->runId);
        static::assertSame(1, $result->attempt);
    }

    #[Test]
    public function spawnWithOptions(): void
    {
        $this->absurd->registerTask('my-task', static fn(array $p, TaskContext $ctx) => yield from []);

        $result = $this->absurd->spawn('my-task', ['data' => 'test'], new SpawnOptions(
            maxAttempts: 3,
            retryStrategy: RetryStrategy::fixed(10),
            headers: ['source' => 'test'],
        ));

        static::assertNotEmpty($result->taskId);
        static::assertSame(1, $result->attempt);
    }

    #[Test]
    public function emitEventWithEmptyNameThrows(): void
    {
        $this->expectException(TaskExecutionError::class);
        $this->expectExceptionMessage('eventName must be a non-empty string');

        $this->absurd->emitEvent('');
    }

    #[Test]
    public function emitEventSucceeds(): void
    {
        $this->absurd->emitEvent('test-event', ['key' => 'value']);

        // No exception means success
        static::assertTrue(true);
    }

    #[Test]
    public function claimTasksReturnsEmptyWhenNoTasks(): void
    {
        $tasks = $this->absurd->claimTasks();

        static::assertSame([], $tasks);
    }

    #[Test]
    public function claimTasksReturnsSpawnedTask(): void
    {
        $this->absurd->registerTask('claimable-task', static fn(array $p, TaskContext $ctx) => yield from []);
        $this->absurd->spawn('claimable-task', ['test' => 'data']);

        $tasks = $this->absurd->claimTasks();

        static::assertCount(1, $tasks);
        static::assertSame('claimable-task', $tasks[0]->taskName);
    }

    #[Test]
    public function executeTaskRunsHandler(): void
    {
        $executed = false;

        $this->absurd->registerTask('work-task', static function (array $params, TaskContext $ctx) use (&$executed) {
            $executed = true;
            return $params;
        });

        $this->absurd->spawn('work-task', ['value' => 42]);
        $this->processAllTasks();

        static::assertTrue($executed);
    }
}
