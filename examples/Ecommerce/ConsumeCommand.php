<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Psr\Log\LoggerInterface;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Event\TaskErrorEvent;
use Ruudk\Absurd\Worker\WorkerOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Worker command that processes order fulfillment tasks.
 *
 * Demonstrates:
 * - Registering multiple task types
 * - TaskErrorEvent listener for centralized error handling
 * - Graceful shutdown with signal handling
 */
#[AsCommand(name: 'consume', description: 'Start the order fulfillment worker')]
final class ConsumeCommand extends Command
{
    public function __construct(
        private readonly Absurd $absurd,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcher $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create queue if it doesn't exist
        $this->absurd->createQueue();

        // Register TaskErrorEvent listener for centralized error handling
        $this->eventDispatcher->addListener(TaskErrorEvent::class, function (TaskErrorEvent $event): void {
            $this->logger->error('Task execution failed: {message}', [
                'message' => $event->exception->getMessage(),
                'taskId' => $event->task?->taskId,
                'taskName' => $event->task?->taskName,
                'exception' => $event->exception::class,
            ]);
        });

        // Register main orchestrator task
        $this->absurd->registerTask('order-fulfillment', new OrderFulfillmentTask($this->absurd, $this->logger));

        // Register sub-tasks (spawned by OrderFulfillmentTask)
        $this->absurd->registerTask('check-inventory', new CheckInventoryTask($this->logger));
        $this->absurd->registerTask('fraud-check', new FraudCheckTask($this->absurd, $this->logger));
        $this->absurd->registerTask('send-notification', new SendNotificationTask($this->logger));

        $this->logger->info('Order Fulfillment Worker started (Press Ctrl+C to stop)');
        $this->logger->info('Registered: order-fulfillment, check-inventory, fraud-check, send-notification');

        $worker = $this->absurd->startWorker(new WorkerOptions(
            workerId: 'order-fulfillment-worker',
            logger: $this->logger,
        ));

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
