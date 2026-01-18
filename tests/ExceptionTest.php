<?php declare(strict_types=1);

namespace Ruudk\Absurd;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Exception\SuspendTask;
use Ruudk\Absurd\Exception\TaskExecutionError;
use Ruudk\Absurd\Exception\TimeoutError;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function timeoutErrorHasCorrectMessage(): void
    {
        $error = new TimeoutError('payment-received');

        static::assertSame('Timed out waiting for event "payment-received"', $error->getMessage());
        static::assertInstanceOf(Exception::class, $error);
    }

    #[Test]
    public function suspendTaskHasCorrectMessage(): void
    {
        $error = new SuspendTask();

        static::assertSame('Task suspended', $error->getMessage());
        static::assertInstanceOf(Exception::class, $error);
    }

    #[Test]
    public function taskExecutionErrorHasCorrectMessage(): void
    {
        $error = new TaskExecutionError('Something went wrong');

        static::assertSame('Something went wrong', $error->getMessage());
        static::assertInstanceOf(Exception::class, $error);
    }

    #[Test]
    public function taskExecutionErrorCanHavePreviousException(): void
    {
        $previous = new Exception('Original error');
        $error = new TaskExecutionError('Wrapped error', $previous);

        static::assertSame('Wrapped error', $error->getMessage());
        static::assertSame($previous, $error->getPrevious());
    }
}
