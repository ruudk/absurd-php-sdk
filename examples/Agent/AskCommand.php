<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Agent;

use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Task\SpawnOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive chat command that spawns tasks through Absurd.
 *
 * This command does NOT communicate with OpenAI directly - it only
 * spawns tasks through Absurd and waits for results. The actual
 * OpenAI communication happens in the worker (ConsumeCommand).
 */
#[AsCommand(name: 'ask', description: 'Interactive AI chat (spawns tasks through Absurd)')]
final class AskCommand extends Command
{
    private const MAX_WAIT_SECONDS = 60;
    private const POLL_INTERVAL = 0.2;

    public function __construct(
        private readonly Absurd $absurd,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('AI Agent - Interactive Chat');
        $io->text([
            'Type your messages below. The agent has access to tools:',
            '  - get_weather: Get weather for a location',
            '  - search_web: Search the web',
            '  - calculate: Evaluate math expressions',
            '',
            'Type "quit" or "exit" to end the session.',
            '',
            '<comment>NOTE: Make sure "consume" is running in another terminal!</comment>',
        ]);
        $io->newLine();

        // Create queue if it doesn't exist
        $this->absurd->createQueue();

        $systemPrompt = 'You are a helpful AI assistant with access to tools. Use them when needed to answer questions accurately. Be concise in your responses.';

        /** @var list<array{role: string, content?: string, tool_calls?: array, tool_call_id?: string}> $conversationHistory */
        $conversationHistory = [];

        while (true) {
            $output->write("\n<fg=cyan>You:</> ");
            $line = fgets(STDIN);
            $userInput = trim($line !== false ? $line : '');

            if ($userInput === '' || $userInput === 'quit' || $userInput === 'exit') {
                $io->newLine();
                $io->success('Goodbye!');
                break;
            }

            // Add user message to history
            $conversationHistory[] = ['role' => 'user', 'content' => $userInput];

            // Spawn the task through Absurd
            $spawnResult = $this->absurd->spawn(
                'ai-agent',
                [
                    'prompt' => $userInput,
                    'system' => $systemPrompt,
                    'history' => $conversationHistory,
                ],
                new SpawnOptions(),
                'agents',
            );

            // Wait for the task to be processed by the worker
            $output->write('<fg=green>Agent:</> <fg=gray>...</>');
            $taskInfo = null;
            $waited = 0.0;

            while ($waited < self::MAX_WAIT_SECONDS) {
                $taskInfo = $this->absurd->getTask($spawnResult->taskId);

                if ($taskInfo !== null && $taskInfo->isTerminal()) {
                    break;
                }

                usleep((int) (self::POLL_INTERVAL * 1_000_000));
                $waited += self::POLL_INTERVAL;
            }

            // Clear the waiting indicator line
            $output->write("\r\033[K");

            if ($taskInfo === null) {
                $io->error('Task not found');
                continue;
            }

            if (!$taskInfo->isTerminal()) {
                $io->error('Timeout waiting for task - is "consume" running?');
                continue;
            }

            if ($taskInfo->isFailed()) {
                $io->error('Task failed');
                continue;
            }

            if ($taskInfo->isCancelled()) {
                $io->warning('Task cancelled');
                continue;
            }

            // Extract the response from completed payload
            /** @var array{messages: list<array>, iterations: int, final_response: string}|null $result */
            $result = $taskInfo->completedPayload;

            if ($result === null) {
                $io->error('No result payload');
                continue;
            }

            // Display the final response
            $response = $result['final_response'] ?? '';
            $output->writeln('<fg=green>Agent:</> ' . $response);

            // Update conversation history with messages from this turn
            $newMessages = $result['messages'] ?? [];
            foreach ($newMessages as $msg) {
                // Skip system message
                if (($msg['role'] ?? '') === 'system') {
                    continue;
                }
                // Skip the user message we already added
                if (($msg['role'] ?? '') === 'user' && ($msg['content'] ?? '') === $userInput) {
                    continue;
                }
                $conversationHistory[] = $msg;
            }
        }

        return Command::SUCCESS;
    }
}
