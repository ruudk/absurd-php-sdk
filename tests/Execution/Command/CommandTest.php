<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Execution\AwaitEventOptions;

final class CommandTest extends TestCase
{
    #[Test]
    public function checkpointImplementsCommand(): void
    {
        $command = new Checkpoint('step-1', ['foo' => 'bar']);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame('step-1', $command->name);
        static::assertSame(['foo' => 'bar'], $command->value);
    }

    #[Test]
    public function checkpointAcceptsCallableValue(): void
    {
        $callable = static fn() => 'result';
        $command = new Checkpoint('step-2', $callable);

        static::assertSame($callable, $command->value);
    }

    #[Test]
    public function awaitEventImplementsCommand(): void
    {
        $options = new AwaitEventOptions(stepName: 'wait', timeout: 60);
        $command = new AwaitEvent('payment-received', $options);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame('payment-received', $command->eventName);
        static::assertSame($options, $command->options);
    }

    #[Test]
    public function awaitEventUsesDefaultOptions(): void
    {
        $command = new AwaitEvent('my-event');

        static::assertSame('my-event', $command->eventName);
        static::assertNull($command->options->stepName);
        static::assertNull($command->options->timeout);
    }

    #[Test]
    public function sleepForImplementsCommand(): void
    {
        $command = new SleepFor('delay-step', 30.5);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame('delay-step', $command->stepName);
        static::assertSame(30.5, $command->duration);
    }

    #[Test]
    public function sleepUntilImplementsCommand(): void
    {
        $wakeAt = new DateTimeImmutable('2024-12-31T23:59:59+00:00');
        $command = new SleepUntil('scheduled-step', $wakeAt);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame('scheduled-step', $command->stepName);
        static::assertSame($wakeAt, $command->wakeAt);
    }

    #[Test]
    public function emitEventImplementsCommand(): void
    {
        $command = new EmitEvent('order-completed', ['orderId' => '123']);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame('order-completed', $command->eventName);
        static::assertSame(['orderId' => '123'], $command->payload);
    }

    #[Test]
    public function emitEventWithNullPayload(): void
    {
        $command = new EmitEvent('simple-event');

        static::assertSame('simple-event', $command->eventName);
        static::assertNull($command->payload);
    }

    #[Test]
    public function heartbeatImplementsCommand(): void
    {
        $command = new Heartbeat(60);

        static::assertInstanceOf(Command::class, $command);
        static::assertSame(60, $command->seconds);
    }

    #[Test]
    public function heartbeatWithNullSeconds(): void
    {
        $command = new Heartbeat();

        static::assertInstanceOf(Command::class, $command);
        static::assertNull($command->seconds);
    }
}
