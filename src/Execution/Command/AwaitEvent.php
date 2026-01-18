<?php declare(strict_types=1);

namespace Ruudk\Absurd\Execution\Command;

use Ruudk\Absurd\Execution\AwaitEventOptions;
use Ruudk\Absurd\Execution\Runner;

/**
 * Command to await an event with optional timeout.
 *
 * @internal
 */
final readonly class AwaitEvent implements Command
{
    public function __construct(
        public string $eventName,
        public AwaitEventOptions $options = new AwaitEventOptions(),
    ) {}

    public function execute(Runner $runner): mixed
    {
        return $runner->executeAwaitEvent($this->eventName, $this->options);
    }
}
