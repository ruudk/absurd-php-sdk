<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Agent;

use Psr\Log\LoggerInterface;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Worker\WorkerOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Worker command that processes AI agent tasks.
 *
 * This is where the OpenAI client lives - the consumer is responsible
 * for executing tasks and communicating with external APIs.
 */
#[AsCommand(name: 'consume', description: 'Start the AI agent worker')]
final class ConsumeCommand extends Command
{
    public function __construct(
        private readonly Absurd $absurd,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get OpenAI key from environment
        $openaiKey = $_ENV['OPENAI_KEY'] ?? (getenv('OPENAI_KEY') !== false ? getenv('OPENAI_KEY') : null);
        if ($openaiKey === null || $openaiKey === '') {
            $output->writeln('<error>Error: OPENAI_KEY environment variable is required</error>');
            return Command::FAILURE;
        }

        // Create OpenAI client - only the consumer knows about OpenAI
        $openai = new OpenAIClient($openaiKey);

        // Create queue if it doesn't exist
        $this->absurd->createQueue();

        // Register the agent task (no retries - fail immediately on error)
        $this->absurd->registerTask(
            'ai-agent',
            new AgentTask($openai, $this->logger),
            new RegisterOptions(defaultMaxAttempts: 1),
        );

        $this->logger->info('AI Agent Worker started (Press Ctrl+C to stop)');
        $this->logger->info('Registered task: ai-agent');

        $worker = $this->absurd->startWorker(new WorkerOptions(workerId: 'ai-agent-worker', logger: $this->logger));

        // Set up graceful shutdown handlers
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($worker): void {
            $this->logger->info('Shutting down...');
            $worker->stop();
        });
        pcntl_signal(SIGTERM, $worker->stop(...));

        $worker->start();

        return Command::SUCCESS;
    }
}
