<?php declare(strict_types=1);

namespace Ruudk\Absurd\Worker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Configuration options for a Worker.
 */
final readonly class WorkerOptions
{
    public string $workerId;

    public function __construct(
        ?string $workerId = null,
        public int $claimTimeout = 120,
        public int $batchSize = 1,
        public float $pollInterval = 0.25,
        public bool $fatalOnLeaseTimeout = true,
        public LoggerInterface $logger = new NullLogger(),
    ) {
        $hostname = gethostname();
        $pid = getmypid();
        $this->workerId =
            $workerId ?? ($hostname !== false ? $hostname : 'unknown') . ':' . ($pid !== false ? $pid : 0);
    }
}
