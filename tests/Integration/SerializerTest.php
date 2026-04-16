<?php declare(strict_types=1);

namespace Ruudk\Absurd\Integration;

use PHPUnit\Framework\Attributes\Test;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Serialization\JsonSerializer;
use Ruudk\Absurd\Task\Context;
use Ruudk\Absurd\Task\SpawnOptions;

final class SerializerTest extends IntegrationTestCase
{
    #[Test]
    public function jsonSerializerDeliversArray(): void
    {
        $received = null;

        $this->absurd = new Absurd(self::$pdo, new JsonSerializer(), $this->queueName);

        $this->absurd->registerTask('array-task', static function (array $params, Context $ctx) use (&$received) {
            $received = $params;
        });

        $this->absurd->spawn('array-task', ['id' => 'abc', 'value' => 42]);
        $this->processAllTasks();

        static::assertSame(['id' => 'abc', 'value' => 42], $received);
    }

    #[Test]
    public function jsonSerializerFailsWithTypedPayload(): void
    {
        $this->absurd = new Absurd(self::$pdo, new JsonSerializer(), $this->queueName);

        $this->absurd->registerTask('typed-task-fail', static function (TaskPayload $params, Context $ctx) {});

        $result = $this->absurd->spawn('typed-task-fail', ['id' => 'abc', 'value' => 42], new SpawnOptions(1));

        $this->processAllTasks();

        $taskInfo = $this->absurd->getTask($result->taskId);
        static::assertSame('failed', $taskInfo?->state);
    }
}

final readonly class TaskPayload
{
    public function __construct(
        public string $id,
        public int $value,
    ) {}
}
