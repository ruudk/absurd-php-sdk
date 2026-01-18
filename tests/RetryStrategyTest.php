<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Task\RetryStrategy;

final class RetryStrategyTest extends TestCase
{
    #[Test]
    public function exponentialCreatesCorrectStrategy(): void
    {
        $strategy = RetryStrategy::exponential(5, 1.5, 120);

        static::assertSame('exponential', $strategy->kind);
        static::assertSame(5, $strategy->baseSeconds);
        static::assertSame(1.5, $strategy->factor);
        static::assertSame(120, $strategy->maxSeconds);
    }

    #[Test]
    public function exponentialUsesDefaults(): void
    {
        $strategy = RetryStrategy::exponential();

        static::assertSame('exponential', $strategy->kind);
        static::assertSame(10, $strategy->baseSeconds);
        static::assertSame(2.0, $strategy->factor);
        static::assertSame(300, $strategy->maxSeconds);
    }

    #[Test]
    public function linearCreatesCorrectStrategy(): void
    {
        $strategy = RetryStrategy::linear(15, 600);

        static::assertSame('linear', $strategy->kind);
        static::assertSame(15, $strategy->baseSeconds);
        static::assertNull($strategy->factor);
        static::assertSame(600, $strategy->maxSeconds);
    }

    #[Test]
    public function fixedCreatesCorrectStrategy(): void
    {
        $strategy = RetryStrategy::fixed(30);

        static::assertSame('fixed', $strategy->kind);
        static::assertSame(30, $strategy->baseSeconds);
        static::assertNull($strategy->factor);
        static::assertNull($strategy->maxSeconds);
    }

    #[Test]
    public function jsonSerializeExponential(): void
    {
        $strategy = RetryStrategy::exponential(5, 1.5, 120);

        static::assertSame(
            [
                'kind' => 'exponential',
                'base_seconds' => 5,
                'factor' => 1.5,
                'max_seconds' => 120,
            ],
            $strategy->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeLinear(): void
    {
        $strategy = RetryStrategy::linear(10, 300);

        static::assertSame(
            [
                'kind' => 'linear',
                'base_seconds' => 10,
                'max_seconds' => 300,
            ],
            $strategy->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeFixed(): void
    {
        $strategy = RetryStrategy::fixed(30);

        static::assertSame(
            [
                'kind' => 'fixed',
                'base_seconds' => 30,
            ],
            $strategy->jsonSerialize(),
        );
    }

    #[Test]
    public function noneCreatesCorrectStrategy(): void
    {
        $strategy = RetryStrategy::none();

        static::assertSame('none', $strategy->kind);
        static::assertNull($strategy->baseSeconds);
        static::assertNull($strategy->factor);
        static::assertNull($strategy->maxSeconds);
    }

    #[Test]
    public function jsonSerializeNone(): void
    {
        $strategy = RetryStrategy::none();

        static::assertSame(['kind' => 'none'], $strategy->jsonSerialize());
    }
}
