<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\Runner;

/**
 * Command to emit an event.
 *
 * @internal
 */
final readonly class EmitEvent implements Command
{
    public function __construct(
        public string $eventName,
        public mixed $payload = null,
    ) {}

    public function execute(Runner $runner): null
    {
        $runner->executeEmitEvent($this->eventName, $this->payload);

        return null;
    }
}
