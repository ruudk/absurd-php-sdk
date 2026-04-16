<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use Ruudk\Absurd\Connection\Connection;
use Ruudk\Absurd\Serialization\Serializer;

/**
 * Context for task execution, containing all dependencies needed by Runner.
 *
 * @internal
 */
final readonly class Context
{
    public function __construct(
        public Connection $connection,
        public string $queueName,
        public int $claimTimeout,
        public Serializer $serializer,
    ) {}
}
