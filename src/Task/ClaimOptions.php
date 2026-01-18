<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

/**
 * Options for claiming tasks.
 */
final readonly class ClaimOptions
{
    public function __construct(
        public string $workerId = 'worker',
        public int $claimTimeout = 120,
        public int $batchSize = 1,
    ) {}
}
