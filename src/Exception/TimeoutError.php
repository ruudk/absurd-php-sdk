<?php declare(strict_types=1);

namespace Ruudk\Absurd\Exception;

use Exception;

/**
 * Exception thrown when awaiting an event times out.
 */
final class TimeoutError extends Exception
{
    public function __construct(string $eventName)
    {
        parent::__construct(sprintf('Timed out waiting for event "%s"', $eventName));
    }
}
