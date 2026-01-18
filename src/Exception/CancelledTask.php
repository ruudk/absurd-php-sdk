<?php declare(strict_types=1);

namespace Ruudk\Absurd\Exception;

use Exception;

/**
 * Thrown when a task has been cancelled.
 *
 * This exception is used for control flow when a task is cancelled via cancelTask().
 * Running tasks will receive this exception at their next checkpoint, heartbeat,
 * or await event call.
 */
final class CancelledTask extends Exception {}
