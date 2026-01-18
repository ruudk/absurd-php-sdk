<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that colorizes log messages based on task ID.
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
    private array $taskColors = [];

    public function __invoke(LogRecord $record): LogRecord
    {
        $taskId = $record->context['taskId'] ?? null;

        if ($taskId === null) {
            return $record;
        }

        if (!isset($this->taskColors[$taskId])) {
            $this->taskColors[$taskId] = self::COLORS[$this->colorIndex % count(self::COLORS)];
            $this->colorIndex++;
        }

        $color = $this->taskColors[$taskId];
        $message = sprintf('%s[%s]%s %s', $color, $taskId, self::RESET, $record->message);

        return $record->with(message: $message);
    }
}
