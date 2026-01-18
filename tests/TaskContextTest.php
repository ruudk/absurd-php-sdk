<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use DateTimeImmutable;
use Fiber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Execution\AwaitEventOptions;
use Ruudk\Absurd\Execution\Command\AwaitEvent;
use Ruudk\Absurd\Execution\Command\Checkpoint;
use Ruudk\Absurd\Execution\Command\EmitEvent;
use Ruudk\Absurd\Execution\Command\Heartbeat;
use Ruudk\Absurd\Execution\Command\SleepFor;
use Ruudk\Absurd\Execution\Command\SleepUntil;

final class TaskContextTest extends TestCase
{
    #[Test]
    public function constructsWithCorrectProperties(): void
    {
        $ctx = new Context('task-123', 'run-456', 3);

        static::assertSame('task-123', $ctx->taskId);
        static::assertSame('run-456', $ctx->runId);
        static::assertSame(3, $ctx->attempt);
    }

    #[Test]
    public function stepSuspendsWithCheckpointCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static fn() => $ctx->step('my-step', ['data' => 'value']));

        $command = $fiber->start();

        static::assertInstanceOf(Checkpoint::class, $command);
        static::assertSame('my-step', $command->name);
        static::assertSame(['data' => 'value'], $command->value);
    }

    #[Test]
    public function stepReturnsResumedValue(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static fn() => $ctx->step('my-step', static fn() => 'computed'));

        $fiber->start();
        $fiber->resume('resumed-value');
        $result = $fiber->getReturn();

        static::assertSame('resumed-value', $result);
    }

    #[Test]
    public function awaitEventSuspendsWithAwaitEventCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static fn() => $ctx->awaitEvent('payment-received', new AwaitEventOptions(timeout: 60)));

        $command = $fiber->start();

        static::assertInstanceOf(AwaitEvent::class, $command);
        static::assertSame('payment-received', $command->eventName);
        static::assertSame(60, $command->options->timeout);
    }

    #[Test]
    public function sleepForSuspendsWithSleepForCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static function () use ($ctx) {
            $ctx->sleepFor('delay', 30.0);
            return 'done';
        });

        $command = $fiber->start();

        static::assertInstanceOf(SleepFor::class, $command);
        static::assertSame('delay', $command->stepName);
        static::assertSame(30.0, $command->duration);
    }

    #[Test]
    public function sleepUntilSuspendsWithSleepUntilCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);
        $wakeAt = new DateTimeImmutable('2025-01-01T00:00:00+00:00');

        $fiber = new Fiber(static function () use ($ctx, $wakeAt) {
            $ctx->sleepUntil('scheduled', $wakeAt);
            return 'done';
        });

        $command = $fiber->start();

        static::assertInstanceOf(SleepUntil::class, $command);
        static::assertSame('scheduled', $command->stepName);
        static::assertSame($wakeAt, $command->wakeAt);
    }

    #[Test]
    public function emitEventSuspendsWithEmitEventCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static function () use ($ctx) {
            $ctx->emitEvent('order-shipped', ['trackingNumber' => 'ABC123']);
            return 'done';
        });

        $command = $fiber->start();

        static::assertInstanceOf(EmitEvent::class, $command);
        static::assertSame('order-shipped', $command->eventName);
        static::assertSame(['trackingNumber' => 'ABC123'], $command->payload);
    }

    #[Test]
    public function multipleCommandsInSequence(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static function () use ($ctx) {
            $step1 = $ctx->step('step-1', 'value-1');
            $step2 = $ctx->step('step-2', 'value-2');
            return [$step1, $step2];
        });

        $cmd1 = $fiber->start();
        static::assertInstanceOf(Checkpoint::class, $cmd1);
        static::assertSame('step-1', $cmd1->name);

        $cmd2 = $fiber->resume('result-1');
        static::assertInstanceOf(Checkpoint::class, $cmd2);
        static::assertSame('step-2', $cmd2->name);

        $fiber->resume('result-2');
        $result = $fiber->getReturn();
        static::assertSame(['result-1', 'result-2'], $result);
    }

    #[Test]
    public function heartbeatSuspendsWithHeartbeatCommand(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static function () use ($ctx) {
            $ctx->heartbeat(60);
            return 'done';
        });

        $command = $fiber->start();

        static::assertInstanceOf(Heartbeat::class, $command);
        static::assertSame(60, $command->seconds);
    }

    #[Test]
    public function heartbeatWithNullUsesDefaultTimeout(): void
    {
        $ctx = new Context('task-1', 'run-1', 1);

        $fiber = new Fiber(static function () use ($ctx) {
            $ctx->heartbeat();
            return 'done';
        });

        $command = $fiber->start();

        static::assertInstanceOf(Heartbeat::class, $command);
        static::assertNull($command->seconds);
    }
}
