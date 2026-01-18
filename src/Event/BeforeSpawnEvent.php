<?php declare(strict_types=1);

namespace Ruudk\Absurd\Event;

use Ruudk\Absurd\Task\SpawnOptions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a task is spawned.
 *
 * Listeners can modify the spawn options (e.g., to inject headers for tracing).
 */
final class BeforeSpawnEvent extends Event
{
    public function __construct(
        public readonly string $taskName,
        public readonly mixed $params,
        public SpawnOptions $options,
    ) {}
}
