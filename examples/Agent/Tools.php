<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Agent;

/**
 * Tool definitions and handlers for the AI agent.
 */
final class Tools
{
    /**
     * Get all available tool definitions for OpenAI.
     *
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public static function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Get the current weather in a given location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The city and state, e.g. San Francisco, CA',
                            ],
                            'unit' => [
                                'type' => 'string',
                                'enum' => ['celsius', 'fahrenheit'],
                                'description' => 'The temperature unit to use',
                            ],
                        ],
                        'required' => ['location'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_web',
                    'description' => 'Search the web for information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search query',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate',
                    'description' => 'Perform a mathematical calculation',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'expression' => [
                                'type' => 'string',
                                'description' => 'The mathematical expression to evaluate, e.g. "2 + 2 * 3"',
                            ],
                        ],
                        'required' => ['expression'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call and return the result.
     *
     * @param array{name: string, arguments: string} $toolCall
     */
    public static function execute(array $toolCall): string
    {
        $name = $toolCall['name'];
        /** @var array<string, mixed> $args */
        $args = json_decode($toolCall['arguments'], true) ?? [];

        return match ($name) {
            'get_weather' => self::getWeather($args['location'] ?? 'Unknown', $args['unit'] ?? 'celsius'),
            'search_web' => self::searchWeb($args['query'] ?? ''),
            'calculate' => self::calculate($args['expression'] ?? '0'),
            default => "Unknown tool: {$name}",
        };
    }

    private static function getWeather(string $location, string $unit): string
    {
        // Simulated weather data
        $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy', 'windy'];
        $condition = $conditions[array_rand($conditions)];
        $temp = $unit === 'fahrenheit' ? rand(32, 95) : rand(0, 35);
        $unitSymbol = $unit === 'fahrenheit' ? 'F' : 'C';

        return sprintf(
            'The weather in %s is currently %s with a temperature of %d°%s.',
            $location,
            $condition,
            $temp,
            $unitSymbol,
        );
    }

    private static function searchWeb(string $query): string
    {
        // Simulated search results
        return sprintf(
            'Search results for "%s": Found several relevant articles discussing %s. '
            . 'The most popular result suggests checking official documentation for accurate information.',
            $query,
            $query,
        );
    }

    private static function calculate(string $expression): string
    {
        // Simple safe math evaluation using BC Math (no eval)
        // Only supports basic arithmetic: +, -, *, /
        $expression = preg_replace('/\s+/', '', $expression);
        if ($expression === null || $expression === '') {
            return 'Invalid expression';
        }

        // Validate: only allow digits, decimal points, and basic operators
        if (!preg_match('/^[\d+\-*\/.()]+$/', $expression)) {
            return 'Invalid expression: only basic arithmetic is supported';
        }

        try {
            // Parse and evaluate simple expressions without eval
            $result = self::evaluateExpression($expression);
            return sprintf('The result of %s is %s', $expression, $result);
        } catch (\Throwable) {
            return 'Could not evaluate expression';
        }
    }

    /**
     * Simple recursive descent parser for basic arithmetic.
     */
    private static function evaluateExpression(string $expr): string
    {
        // Remove parentheses by evaluating inner expressions first
        while (preg_match('/\(([^()]+)\)/', $expr, $matches)) {
            $inner = self::evaluateExpression($matches[1]);
            $expr = str_replace($matches[0], $inner, $expr);
        }

        // Handle addition and subtraction (lowest precedence)
        if (preg_match('/^(.+?)([+\-])([^+\-]+)$/', $expr, $matches)) {
            $left = self::evaluateExpression($matches[1]);
            $right = self::evaluateExpression($matches[3]);
            return $matches[2] === '+' ? bcadd($left, $right, 10) : bcsub($left, $right, 10);
        }

        // Handle multiplication and division
        if (preg_match('/^(.+?)([*\/])([^*\/]+)$/', $expr, $matches)) {
            $left = self::evaluateExpression($matches[1]);
            $right = self::evaluateExpression($matches[3]);
            return $matches[2] === '*' ? bcmul($left, $right, 10) : bcdiv($left, $right, 10);
        }

        // Base case: just a number
        return $expr;
    }
}
