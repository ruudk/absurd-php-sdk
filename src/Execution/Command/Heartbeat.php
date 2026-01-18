<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;

/**
 * Command to extend the current run's lease.
 *
 * @internal
 */
final readonly class Heartbeat implements Command
{
    public function __construct(
        public ?int $seconds = null,
    ) {}

    public function execute(Runner $runner): mixed
    {
        $runner->executeHeartbeat($this->seconds);
        return null;
    }
}
