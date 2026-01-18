<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples;

use DateTimeImmutable;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Task\CancellationPolicy;
use Ruudk\Absurd\Task\RetryStrategy;
use Ruudk\Absurd\Task\SpawnOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Producer command that creates orders and simulates events.
 *
 * Demonstrates:
 * - listQueues() for queue discovery
 * - spawn() with typed payload (OrderPayload)
 * - SpawnResult.created to detect new vs cached tasks
 * - Retry strategies and cancellation policies
 * - Event emission for payment webhook
 * - Headers/tracing propagation
 * - Idempotency key usage
 * - cancelTask() for order cancellation
 */
#[AsCommand(name: 'produce', description: 'Create an order and simulate events')]
final class ProduceCommand extends Command
{
    private const HABITAT_URL = 'http://localhost:7890';

    public function __construct(
        private readonly Absurd $absurd,
        private readonly TracingSubscriber $tracingSubscriber,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('scheduled', 's', InputOption::VALUE_OPTIONAL, 'Schedule delivery for N minutes from now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Step 1: Show available queues using listQueues()
        $queues = $this->absurd->listQueues();
        $io->section('Available Queues');
        if ($queues === []) {
            $io->warning('No queues found. The worker will create the "orders" queue.');
        }

        if ($queues !== []) {
            $io->listing($queues);
        }

        // Set trace_id for this request (simulating incoming HTTP request with trace header)
        $traceId = sprintf('req-%08x', random_int(0, 0xFFFFFFFF));
        $this->tracingSubscriber->setTraceId($traceId);

        // Generate order data
        $orderId = 'ord-' . bin2hex(random_bytes(8));
        $customerId = 'cust-' . bin2hex(random_bytes(4));
        $email = sprintf('customer-%s@example.com', bin2hex(random_bytes(3)));

        // Create typed payload
        $scheduledMinutes = $input->getOption('scheduled');
        $scheduledDelivery = $scheduledMinutes !== null
            ? new DateTimeImmutable(sprintf('+%d minutes', (int) $scheduledMinutes))
            : null;

        $payload = new OrderPayload(
            orderId: $orderId,
            customerId: $customerId,
            email: $email,
            items: [
                ['sku' => 'WIDGET-001', 'qty' => 2, 'price' => 2999, 'warehouse' => 'WH-1'],
                ['sku' => 'GADGET-042', 'qty' => 1, 'price' => 7999, 'warehouse' => 'WH-2'],
            ],
            shippingAddress: [
                'street' => '123 Main St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'country' => 'US',
            ],
            totalCents: 13997,
            paymentMethodId: 'pm_' . bin2hex(random_bytes(12)),
            scheduledDelivery: $scheduledDelivery,
        );

        // Spawn the order fulfillment task with all options demonstrated
        $io->section('Spawning Order');

        $result = $this->absurd->spawn(
            'order-fulfillment',
            $payload,
            new SpawnOptions(
                maxAttempts: 3,
                retryStrategy: RetryStrategy::exponential(5, 2.0, 60),
                cancellation: new CancellationPolicy(maxDuration: 7200), // 2 hour max
                headers: ['source' => 'cli-producer', 'customer_tier' => 'premium'],
                idempotencyKey: sprintf('order:%s', $orderId),
            ),
            'orders',
        );

        // Log whether task was new or from idempotency cache
        $createdStatus = $result->created ? 'NEW TASK' : 'FROM CACHE';
        $io->success(sprintf(
            "Order %s spawned [%s]\n  Task ID: %s\n  Run ID: %s\n  Trace ID: %s\n  Habitat: %s/tasks/%s",
            $orderId,
            $createdStatus,
            $result->taskId,
            $result->runId,
            $traceId,
            self::HABITAT_URL,
            $result->taskId,
        ));

        if ($scheduledDelivery !== null) {
            $io->note(sprintf('Scheduled delivery: %s', $scheduledDelivery->format('Y-m-d H:i:s')));
        }

        // Step 2: Simulate payment webhook
        $io->section('Payment Processing');
        $io->text('The order is waiting for a payment confirmation webhook.');

        $paymentChoice = $io->choice(
            'Simulate payment result:',
            [
                'success' => 'Payment successful',
                'declined' => 'Payment declined (insufficient funds)',
                'cancel' => 'Cancel the order',
                'skip' => 'Skip (workflow will timeout after 5 minutes)',
            ],
            'success',
        );

        if ($paymentChoice === 'cancel') {
            $this->absurd->cancelTask($result->taskId);
            $io->success(sprintf('Order cancelled: %s', $result->taskId));
            $io->text(sprintf('View in Habitat: %s/tasks/%s', self::HABITAT_URL, $result->taskId));
            return Command::SUCCESS;
        }

        if ($paymentChoice === 'skip') {
            $io->note('Skipped - workflow will timeout waiting for payment webhook');
            return Command::SUCCESS;
        }

        if ($paymentChoice === 'declined') {
            $this->absurd->emitEvent(sprintf('payment-webhook:%s', $orderId), [
                'success' => false,
                'declineReason' => 'insufficient_funds',
            ]);
            $io->warning('Payment declined - order will be cancelled');
            return Command::SUCCESS;
        }

        $transactionId = 'txn_' . bin2hex(random_bytes(12));
        $this->absurd->emitEvent(sprintf('payment-webhook:%s', $orderId), [
            'success' => true,
            'transactionId' => $transactionId,
        ]);
        $io->success(sprintf('Payment webhook emitted: %s', $transactionId));

        // Summary
        $io->section('Order Summary');
        $io->definitionList(
            ['Order ID' => $orderId],
            ['Task ID' => $result->taskId],
            ['Habitat' => sprintf('%s/tasks/%s', self::HABITAT_URL, $result->taskId)],
            ['Customer' => sprintf('%s (%s)', $customerId, $email)],
            ['Total' => sprintf('$%.2f', $payload->totalCents / 100)],
            ['Items' => count($payload->items)],
            ['Trace ID' => $traceId],
        );

        $io->info('Watch the worker terminal for order processing progress.');
        $io->text('After payment confirmation, the workflow will:');
        $io->listing([
            'Spawn inventory checks per warehouse',
            'Spawn fraud check and await result',
            'Generate shipping label',
            'Emit order-shipped event',
            'Wait 3 seconds (notification delay)',
            'Spawn notification task',
            'Emit order-complete event',
        ]);

        return Command::SUCCESS;
    }
}
