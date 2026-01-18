<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Execution\CheckpointResult;
use Ruudk\Absurd\Task\SpawnResult;

final class ResultTest extends TestCase
{
    #[Test]
    public function spawnResultConstructs(): void
    {
        $result = new SpawnResult(
            taskId: '019a32d3-8425-7ae2-a5af-2f17a6707666',
            runId: '019a32d3-8426-7ae2-a5af-2f17a6707667',
            attempt: 1,
        );

        static::assertSame('019a32d3-8425-7ae2-a5af-2f17a6707666', $result->taskId);
        static::assertSame('019a32d3-8426-7ae2-a5af-2f17a6707667', $result->runId);
        static::assertSame(1, $result->attempt);
        static::assertTrue($result->created);
    }

    #[Test]
    public function spawnResultWithCreatedFalse(): void
    {
        $result = new SpawnResult(
            taskId: '019a32d3-8425-7ae2-a5af-2f17a6707666',
            runId: '019a32d3-8426-7ae2-a5af-2f17a6707667',
            attempt: 1,
            created: false,
        );

        static::assertSame('019a32d3-8425-7ae2-a5af-2f17a6707666', $result->taskId);
        static::assertSame('019a32d3-8426-7ae2-a5af-2f17a6707667', $result->runId);
        static::assertSame(1, $result->attempt);
        static::assertFalse($result->created);
    }

    #[Test]
    public function checkpointResultConstructsWithExistingValue(): void
    {
        $result = new CheckpointResult(exists: true, value: ['foo' => 'bar'], name: 'step-1');

        static::assertTrue($result->exists);
        static::assertSame(['foo' => 'bar'], $result->value);
        static::assertSame('step-1', $result->name);
    }

    #[Test]
    public function checkpointResultConstructsWithoutValue(): void
    {
        $result = new CheckpointResult(exists: false, value: null, name: 'step-2');

        static::assertFalse($result->exists);
        static::assertNull($result->value);
        static::assertSame('step-2', $result->name);
    }
}
