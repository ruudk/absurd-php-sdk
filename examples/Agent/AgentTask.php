<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Agent;

use Ruudk\Absurd\Task\Context as TaskContext;

/**
 * AI Agent task that loops through conversation steps with tool calling.
 *
 * Based on the pattern from https://lucumr.pocoo.org/2025/11/3/absurd-workflows/
 *
 * This demonstrates:
 * - Loop-based agent execution with checkpointing
 * - OpenAI API integration with tool calls
 * - Each iteration is durably checkpointed
 * - Automatic recovery on crash/restart
 * - Replay-aware logging via $ctx->logger (logs are skipped during checkpoint replay)
 */
final readonly class AgentTask
{
    private const MAX_ITERATIONS = 20;

    public function __construct(
        private OpenAIClient $openai,
    ) {}

    /**
     * @param array{prompt: string, system?: string, history?: list<array>} $params
     * @return array{messages: list<array>, iterations: int, final_response: string}
     */
    public function __invoke(array $params, TaskContext $ctx): array
    {
        $systemPrompt =
            $params['system']
            ?? 'You are a helpful AI assistant with access to tools. Use them when needed to answer questions accurately.';

        // Build messages: use history if provided, otherwise start fresh with user prompt
        $history = $params['history'] ?? [];
        /** @var list<array{role: string, content?: string, tool_calls?: array, tool_call_id?: string, name?: string}> $messages */
        $messages = $history !== []
            ? [['role' => 'system', 'content' => $systemPrompt], ...$history]
            : [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $params['prompt']]];

        // taskId and runId are auto-injected by ReplayAwareLogger
        $ctx->logger->info('[IN] User message: {prompt}', [
            'prompt' => $params['prompt'],
        ]);

        $iteration = 0;
        $finalResponse = '';

        while ($iteration < self::MAX_ITERATIONS) {
            $iteration++;

            $ctx->logger->info('Agent iteration {iteration}', [
                'iteration' => $iteration,
                'messageCount' => count($messages),
            ]);

            // Each iteration is checkpointed - if we crash, we resume from here
            // The step name "iteration" is automatically numbered by the checkpoint system
            /** @var array{messages: list<array>, finish_reason: string, response: string} $stepResult */
            $stepResult = $ctx->step('iteration', fn(): array => $this->executeStep($messages));

            // Merge new messages into our conversation
            $messages = array_merge($messages, $stepResult['messages']);
            $finishReason = $stepResult['finish_reason'];

            // Log tool calls if any
            foreach ($stepResult['messages'] as $msg) {
                if (isset($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $ctx->logger->info('[TOOL] Calling {tool}: {args}', [
                            'tool' => $tc['function']['name'],
                            'args' => $tc['function']['arguments'],
                        ]);
                    }
                }
                if (($msg['role'] ?? '') === 'tool') {
                    $ctx->logger->info('[TOOL] Result: {result}', [
                        'result' => $msg['content'] ?? '',
                    ]);
                }
            }

            // Log assistant response if any
            if ($stepResult['response'] !== '') {
                $ctx->logger->info('[OUT] Assistant response: {response}', [
                    'response' => $stepResult['response'],
                ]);
            }

            // If the model didn't request tool calls, we're done
            if ($finishReason !== 'tool_calls') {
                $finalResponse = $stepResult['response'];
                break;
            }
        }

        if ($iteration >= self::MAX_ITERATIONS) {
            $ctx->logger->warning('Agent reached max iterations', [
                'maxIterations' => self::MAX_ITERATIONS,
            ]);
        }

        $ctx->logger->info('Agent completed in {iterations} iteration(s)', [
            'iterations' => $iteration,
        ]);

        return [
            'messages' => $messages,
            'iterations' => $iteration,
            'final_response' => $finalResponse,
        ];
    }

    /**
     * Execute a single step: call OpenAI and handle any tool calls.
     *
     * @param list<array{role: string, content?: string, tool_calls?: array, tool_call_id?: string, name?: string}> $messages
     * @return array{messages: list<array>, finish_reason: string, response: string}
     */
    private function executeStep(array $messages): array
    {
        $result = $this->openai->chat($messages, Tools::definitions());

        /** @var array{role: string, content?: string|null, tool_calls?: list<array{id: string, type: string, function: array{name: string, arguments: string}}>} $assistantMessage */
        $assistantMessage = $result['message'];
        $finishReason = $result['finish_reason'];

        /** @var list<array> $newMessages */
        $newMessages = [$assistantMessage];
        $response = $assistantMessage['content'] ?? '';

        // If the model wants to call tools, execute them and add results
        if ($finishReason === 'tool_calls' && isset($assistantMessage['tool_calls'])) {
            foreach ($assistantMessage['tool_calls'] as $toolCall) {
                $toolName = $toolCall['function']['name'];
                $toolArgs = $toolCall['function']['arguments'];

                $toolResult = Tools::execute([
                    'name' => $toolName,
                    'arguments' => $toolArgs,
                ]);

                $newMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => $toolResult,
                ];
            }
        }

        return [
            'messages' => $newMessages,
            'finish_reason' => $finishReason,
            'response' => $response,
        ];
    }
}
