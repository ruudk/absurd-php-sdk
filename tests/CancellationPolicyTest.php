<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Task\CancellationPolicy;

final class CancellationPolicyTest extends TestCase
{
    #[Test]
    public function constructsWithAllOptions(): void
    {
        $policy = new CancellationPolicy(maxDuration: 3600, maxDelay: 300);

        static::assertSame(3600, $policy->maxDuration);
        static::assertSame(300, $policy->maxDelay);
    }

    #[Test]
    public function constructsWithDefaults(): void
    {
        $policy = new CancellationPolicy();

        static::assertNull($policy->maxDuration);
        static::assertNull($policy->maxDelay);
    }

    #[Test]
    public function jsonSerializeWithAllOptions(): void
    {
        $policy = new CancellationPolicy(maxDuration: 3600, maxDelay: 300);

        static::assertSame(
            [
                'max_duration' => 3600,
                'max_delay' => 300,
            ],
            $policy->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeWithOnlyMaxDuration(): void
    {
        $policy = new CancellationPolicy(maxDuration: 7200);

        static::assertSame(
            [
                'max_duration' => 7200,
            ],
            $policy->jsonSerialize(),
        );
    }

    #[Test]
    public function jsonSerializeWithNoOptions(): void
    {
        $policy = new CancellationPolicy();

        static::assertSame([], $policy->jsonSerialize());
    }
}
