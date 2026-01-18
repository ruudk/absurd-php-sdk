<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Execution\AwaitEventOptions;
use Ruudk\Absurd\Task\CancellationPolicy;
use Ruudk\Absurd\Task\ClaimOptions;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Worker\WorkerOptions;

final class OptionsTest extends TestCase
{
    #[Test]
    public function awaitEventOptionsConstructsWithAllOptions(): void
    {
        $options = new AwaitEventOptions(stepName: 'wait-payment', timeout: 300);

        static::assertSame('wait-payment', $options->stepName);
        static::assertSame(300, $options->timeout);
    }

    #[Test]
    public function awaitEventOptionsConstructsWithDefaults(): void
    {
        $options = new AwaitEventOptions();

        static::assertNull($options->stepName);
        static::assertNull($options->timeout);
    }

    #[Test]
    public function claimTasksOptionsConstructsWithAllOptions(): void
    {
        $options = new ClaimOptions(workerId: 'worker-1', claimTimeout: 60, batchSize: 5);

        static::assertSame('worker-1', $options->workerId);
        static::assertSame(60, $options->claimTimeout);
        static::assertSame(5, $options->batchSize);
    }

    #[Test]
    public function claimTasksOptionsConstructsWithDefaults(): void
    {
        $options = new ClaimOptions();

        static::assertSame('worker', $options->workerId);
        static::assertSame(120, $options->claimTimeout);
        static::assertSame(1, $options->batchSize);
    }

    #[Test]
    public function registerTaskOptionsConstructsWithAllOptions(): void
    {
        $cancellation = new CancellationPolicy(maxDuration: 7200);
        $options = new RegisterOptions(
            queue: 'high-priority',
            defaultMaxAttempts: 10,
            defaultCancellation: $cancellation,
        );

        static::assertSame('high-priority', $options->queue);
        static::assertSame(10, $options->defaultMaxAttempts);
        static::assertSame($cancellation, $options->defaultCancellation);
    }

    #[Test]
    public function registerTaskOptionsConstructsWithDefaults(): void
    {
        $options = new RegisterOptions();

        static::assertNull($options->queue);
        static::assertNull($options->defaultMaxAttempts);
        static::assertNull($options->defaultCancellation);
    }

    #[Test]
    public function workerOptionsConstructsWithAllOptions(): void
    {
        $options = new WorkerOptions(
            workerId: 'my-worker',
            claimTimeout: 60,
            batchSize: 10,
            pollInterval: 0.5,
            fatalOnLeaseTimeout: false,
        );

        static::assertSame('my-worker', $options->workerId);
        static::assertSame(60, $options->claimTimeout);
        static::assertSame(10, $options->batchSize);
        static::assertSame(0.5, $options->pollInterval);
        static::assertFalse($options->fatalOnLeaseTimeout);
    }

    #[Test]
    public function workerOptionsGeneratesDefaultWorkerId(): void
    {
        $options = new WorkerOptions();

        static::assertMatchesRegularExpression('/^.+:\d+$/', $options->workerId);
        static::assertSame(120, $options->claimTimeout);
        static::assertSame(1, $options->batchSize);
        static::assertSame(0.25, $options->pollInterval);
        static::assertTrue($options->fatalOnLeaseTimeout);
    }
}
