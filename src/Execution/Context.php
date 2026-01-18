<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution;

use PDO;
use Ruudk\Absurd\Serialization\Serializer;

/**
 * Context for task execution, containing all dependencies needed by Runner.
 *
 * @internal
 */
final readonly class Context
{
    public function __construct(
        public PDO $pdo,
        public string $queueName,
        public int $claimTimeout,
        public Serializer $serializer,
    ) {}
}
