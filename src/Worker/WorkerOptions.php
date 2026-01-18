<?php declare(strict_types=1);

namespace Ruudk\Absurd\Worker;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Configuration options for a Worker.
 */
final readonly class WorkerOptions
{
    public string $workerId;

    /**
     * @throws InvalidArgumentException If claimTimeout, batchSize, or pollInterval are invalid
     */
    public function __construct(
        ?string $workerId = null,
        public int $claimTimeout = 120,
        public int $batchSize = 1,
        public float $pollInterval = 0.25,
        public bool $fatalOnLeaseTimeout = true,
        public LoggerInterface $logger = new NullLogger(),
    ) {
        if ($claimTimeout <= 0) {
            throw new InvalidArgumentException('claimTimeout must be greater than 0');
        }
        if ($batchSize <= 0) {
            throw new InvalidArgumentException('batchSize must be greater than 0');
        }
        if ($pollInterval <= 0) {
            throw new InvalidArgumentException('pollInterval must be greater than 0');
        }

        $hostname = gethostname();
        $pid = getmypid();
        $this->workerId =
            $workerId ?? ($hostname !== false ? $hostname : 'unknown') . ':' . ($pid !== false ? $pid : 0);
    }
}
