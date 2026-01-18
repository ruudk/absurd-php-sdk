<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Task\CancellationPolicy;
use Ruudk\Absurd\Task\RetryStrategy;
use Ruudk\Absurd\Task\SpawnOptions;

final class SpawnOptionsTest extends TestCase
{
    #[Test]
    public function constructsWithAllOptions(): void
    {
        $retry = RetryStrategy::exponential();
        $cancellation = new CancellationPolicy(maxDuration: 3600);

        $options = new SpawnOptions(maxAttempts: 5, retryStrategy: $retry, cancellation: $cancellation, headers: [
            'source' => 'test',
        ]);

        static::assertSame(5, $options->maxAttempts);
        static::assertSame($retry, $options->retryStrategy);
        static::assertSame($cancellation, $options->cancellation);
        static::assertSame(['source' => 'test'], $options->headers);
    }

    #[Test]
    public function constructsWithDefaults(): void
    {
        $options = new SpawnOptions();

        static::assertNull($options->maxAttempts);
        static::assertNull($options->retryStrategy);
        static::assertNull($options->cancellation);
        static::assertNull($options->headers);
    }

    #[Test]
    public function jsonSerializeWithAllOptions(): void
    {
        $options = new SpawnOptions(
            maxAttempts: 3,
            retryStrategy: RetryStrategy::fixed(10),
            cancellation: new CancellationPolicy(maxDuration: 1800),
            headers: ['env' => 'prod'],
        );

        static::assertSame(
            [
                'max_attempts' => 3,
                'retry_strategy' => ['kind' => 'fixed', 'base_seconds' => 10],
                'cancellation' => ['max_duration' => 1800],
                'headers' => ['env' => 'prod'],
            ],
            $options->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeOmitsEmptyCancellation(): void
    {
        $options = new SpawnOptions(maxAttempts: 2, cancellation: new CancellationPolicy());

        static::assertSame(
            [
                'max_attempts' => 2,
            ],
            $options->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeWithNoOptions(): void
    {
        $options = new SpawnOptions();

        static::assertSame([], $options->jsonSerialize());
    }
}
