<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that colorizes log messages based on run ID.
 *
 * Each task execution run gets a unique color, so resumed tasks
 * or retries will have different colors than the original run.
 */
final class ColorizedTaskProcessor implements ProcessorInterface
{
    private const COLORS = [
        "\033[33m", // yellow
        "\033[34m", // blue
        "\033[35m", // magenta
        "\033[36m", // cyan
    ];

    private const RESET = "\033[0m";

    private int $colorIndex = 0;

    /** @var array<string, string> */
    private array $runColors = [];

    public function __invoke(LogRecord $record): LogRecord
    {
        $taskId = $record->context['taskId'] ?? null;
        $runId = $record->context['runId'] ?? null;

        if ($taskId === null) {
            return $record;
        }

        // Use runId for color assignment (each execution gets unique color)
        // Fall back to taskId if runId not available
        $colorKey = $runId ?? $taskId;

        if (!isset($this->runColors[$colorKey])) {
            $this->runColors[$colorKey] = self::COLORS[$this->colorIndex % count(self::COLORS)];
            $this->colorIndex++;
        }

        $color = $this->runColors[$colorKey];
        $message = sprintf('%s[%s]%s %s', $color, $taskId, self::RESET, $record->message);

        return $record->with(message: $message);
    }
}
