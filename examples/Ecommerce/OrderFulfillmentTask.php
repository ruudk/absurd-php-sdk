<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Psr\Log\LoggerInterface;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Exception\TimeoutError;
use Ruudk\Absurd\Execution\AwaitEventOptions;
use Ruudk\Absurd\Task\Context as TaskContext;
use Ruudk\Absurd\Task\RetryStrategy;
use Ruudk\Absurd\Task\SpawnOptions;

/**
 * Main order fulfillment orchestrator.
 *
 * Demonstrates SDK features in a realistic e-commerce scenario:
 * - Typed payloads (OrderPayload)
 * - Checkpoints (step) for validation, payment, shipping
 * - Event waiting (awaitEvent) for payment webhook with timeout
 * - Event emitting (emitEvent) for order-shipped, order-complete
 * - Sleep (sleepFor) for notification delay
 * - Sleep (sleepUntil) for scheduled delivery window
 * - Sub-task spawning for inventory, fraud check, notifications
 * - Retry strategies (exponential, fixed, none)
 * - Idempotency keys for deduplication
 * - SpawnResult.created logging
 * - Headers/tracing propagation
 */
final readonly class OrderFulfillmentTask
{
    public function __construct(
        private Absurd $absurd,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{status: string, orderId: string, trackingNumber?: string, reason?: string}
     */
    public function __invoke(OrderPayload $order, TaskContext $ctx): array
    {
        $traceId = $ctx->headers['trace_id'] ?? 'none';

        $this->log('info', 'Starting order fulfillment for {orderId}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'attempt' => $ctx->attempt,
            'totalCents' => $order->totalCents,
            'itemCount' => count($order->items),
        ]);

        // Step 1: Validate order (checkpointed)
        /** @var array{valid: bool, errors: list<string>} $validation */
        $validation = $ctx->step('validate-order', static function () use ($order): array {
            $errors = [];
            if ($order->totalCents <= 0) {
                $errors[] = 'Invalid order total';
            }
            if ($order->items === []) {
                $errors[] = 'No items in order';
            }
            if ($order->shippingAddress['zip'] === '') {
                $errors[] = 'Missing ZIP code';
            }
            return ['valid' => $errors === [], 'errors' => $errors];
        });

        if (!$validation['valid']) {
            $this->log('warning', 'Order validation failed: {errors}', $ctx, $traceId, [
                'orderId' => $order->orderId,
                'errors' => implode(', ', $validation['errors']),
            ]);
            return ['status' => 'failed', 'orderId' => $order->orderId, 'reason' => 'validation_failed'];
        }

        $this->log('info', 'Order validated successfully', $ctx, $traceId, ['orderId' => $order->orderId]);

        // Step 2: Charge payment (checkpointed)
        /** @var array{chargeId: string, status: string} $charge */
        $charge = $ctx->step('charge-payment', static fn(): array => [
            'chargeId' => 'ch_' . bin2hex(random_bytes(12)),
            'status' => 'pending_webhook',
        ]);

        $this->log('info', 'Payment charge initiated: {chargeId}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'chargeId' => $charge['chargeId'],
        ]);

        // Step 3: Await payment webhook (5 minute timeout)
        try {
            /** @var array{success: bool, transactionId?: string, declineReason?: string} $paymentResult */
            $paymentResult = $ctx->awaitEvent(
                sprintf('payment-webhook:%s', $order->orderId),
                new AwaitEventOptions(stepName: 'await-payment-webhook', timeout: 300),
            );
        } catch (TimeoutError) {
            $this->log('warning', 'Payment webhook timeout for order {orderId}', $ctx, $traceId, [
                'orderId' => $order->orderId,
            ]);
            return ['status' => 'failed', 'orderId' => $order->orderId, 'reason' => 'payment_timeout'];
        }

        if (!$paymentResult['success']) {
            $reason = $paymentResult['declineReason'] ?? 'declined';
            $this->log('warning', 'Payment declined for order {orderId}: {reason}', $ctx, $traceId, [
                'orderId' => $order->orderId,
                'reason' => $reason,
            ]);
            return ['status' => 'failed', 'orderId' => $order->orderId, 'reason' => $reason];
        }

        $transactionId = $paymentResult['transactionId'] ?? '';
        $this->log('info', 'Payment confirmed: {transactionId}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'transactionId' => $transactionId,
        ]);

        // Step 4: Spawn inventory checks per warehouse (with idempotency keys)
        $warehouses = [];
        foreach ($order->items as $item) {
            $warehouse = $item['warehouse'] ?? 'WH-1';
            if (!isset($warehouses[$warehouse])) {
                $warehouses[$warehouse] = [];
            }
            $warehouses[$warehouse][] = ['sku' => $item['sku'], 'qty' => $item['qty']];
        }

        foreach ($warehouses as $warehouseId => $items) {
            $inventoryResult = $this->absurd->spawn(
                'check-inventory',
                [
                    'orderId' => $order->orderId,
                    'warehouseId' => $warehouseId,
                    'items' => $items,
                ],
                new SpawnOptions(
                    maxAttempts: 1,
                    retryStrategy: RetryStrategy::none(),
                    idempotencyKey: sprintf('inventory:%s:%s', $order->orderId, $warehouseId),
                    headers: ['source' => 'order-fulfillment'],
                ),
            );

            $status = $inventoryResult->created ? 'NEW' : 'CACHED';
            $this->log('info', 'Inventory check spawned [{status}] for warehouse {warehouseId}', $ctx, $traceId, [
                'orderId' => $order->orderId,
                'warehouseId' => $warehouseId,
                'taskId' => $inventoryResult->taskId,
                'status' => $status,
            ]);
        }

        // Step 5: Spawn fraud check and await result event
        $fraudSpawnResult = $this->absurd->spawn(
            'fraud-check',
            [
                'orderId' => $order->orderId,
                'customerId' => $order->customerId,
                'totalCents' => $order->totalCents,
                'shippingAddress' => $order->shippingAddress,
            ],
            new SpawnOptions(
                maxAttempts: 3,
                retryStrategy: RetryStrategy::exponential(2, 2.0, 30),
                idempotencyKey: sprintf('fraud:%s', $order->orderId),
                headers: ['source' => 'order-fulfillment'],
            ),
        );

        $fraudStatus = $fraudSpawnResult->created ? 'NEW' : 'CACHED';
        $this->log('info', 'Fraud check spawned [{status}]: {taskId}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'taskId' => $fraudSpawnResult->taskId,
            'status' => $fraudStatus,
        ]);

        // Await fraud check result event
        try {
            /** @var array{approved: bool, score: float, flags: list<string>} $fraudResult */
            $fraudResult = $ctx->awaitEvent(
                sprintf('fraud-result:%s', $order->orderId),
                new AwaitEventOptions(stepName: 'await-fraud-result', timeout: 60),
            );
        } catch (TimeoutError) {
            $this->log('warning', 'Fraud check timeout for order {orderId}', $ctx, $traceId, [
                'orderId' => $order->orderId,
            ]);
            return ['status' => 'failed', 'orderId' => $order->orderId, 'reason' => 'fraud_timeout'];
        }

        if (!$fraudResult['approved']) {
            $this->log('warning', 'Fraud check failed for order {orderId}, score: {score}', $ctx, $traceId, [
                'orderId' => $order->orderId,
                'score' => $fraudResult['score'],
                'flags' => implode(', ', $fraudResult['flags']),
            ]);
            return ['status' => 'failed', 'orderId' => $order->orderId, 'reason' => 'fraud_review'];
        }

        $this->log('info', 'Fraud check passed with score {score}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'score' => $fraudResult['score'],
        ]);

        // Step 6: Generate shipping label (checkpointed)
        // Heartbeat extends the task lease before a potentially long operation
        $ctx->heartbeat(60);

        /** @var array{labelId: string, trackingNumber: string, carrier: string} $shippingLabel */
        $shippingLabel = $ctx->step('generate-shipping-label', static fn(): array => [
            'labelId' => 'lbl_' . bin2hex(random_bytes(8)),
            'trackingNumber' => 'TRK' . random_int(1_000_000, 9_999_999),
            'carrier' => ['USPS', 'UPS', 'FedEx'][random_int(0, 2)],
        ]);

        $this->log('info', 'Shipping label generated: {trackingNumber} via {carrier}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'trackingNumber' => $shippingLabel['trackingNumber'],
            'carrier' => $shippingLabel['carrier'],
        ]);

        // Step 7: Emit order-shipped event
        $ctx->emitEvent(sprintf('order-shipped:%s', $order->orderId), [
            'orderId' => $order->orderId,
            'trackingNumber' => $shippingLabel['trackingNumber'],
            'carrier' => $shippingLabel['carrier'],
        ]);

        $this->log('info', 'Emitted order-shipped event', $ctx, $traceId, ['orderId' => $order->orderId]);

        // Step 8: Sleep before sending notification
        $ctx->sleepFor('notification-delay', 3);

        $this->log('info', 'Notification delay completed', $ctx, $traceId, ['orderId' => $order->orderId]);

        // Step 9: Spawn notification task with idempotency key
        $notificationIdempotencyKey = sprintf('notification:%s:shipped', $order->orderId);
        $notifyResult = $this->absurd->spawn(
            'send-notification',
            [
                'orderId' => $order->orderId,
                'email' => $order->email,
                'type' => 'shipped',
                'trackingNumber' => $shippingLabel['trackingNumber'],
                'carrier' => $shippingLabel['carrier'],
            ],
            new SpawnOptions(
                maxAttempts: 5,
                retryStrategy: RetryStrategy::fixed(10),
                idempotencyKey: $notificationIdempotencyKey,
                headers: ['source' => 'order-fulfillment'],
            ),
        );

        $notifyCreatedStatus = $notifyResult->created ? 'NEW' : 'CACHED';
        $this->log('info', 'Notification task spawned [{status}]: {taskId}', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'taskId' => $notifyResult->taskId,
            'status' => $notifyCreatedStatus,
            'idempotencyKey' => $notificationIdempotencyKey,
        ]);

        // Step 10: Optional sleepUntil for scheduled delivery
        if ($order->scheduledDelivery !== null) {
            $this->log('info', 'Waiting for scheduled delivery window: {deliveryTime}', $ctx, $traceId, [
                'orderId' => $order->orderId,
                'deliveryTime' => $order->scheduledDelivery->format('c'),
            ]);
            $ctx->sleepUntil('await-delivery-window', $order->scheduledDelivery);
        }

        // Step 11: Emit order-complete event
        $ctx->emitEvent(sprintf('order-complete:%s', $order->orderId), [
            'orderId' => $order->orderId,
            'trackingNumber' => $shippingLabel['trackingNumber'],
            'status' => 'completed',
        ]);

        $this->log('info', 'Order fulfillment completed', $ctx, $traceId, [
            'orderId' => $order->orderId,
            'trackingNumber' => $shippingLabel['trackingNumber'],
        ]);

        return [
            'status' => 'completed',
            'orderId' => $order->orderId,
            'trackingNumber' => $shippingLabel['trackingNumber'],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function log(string $level, string $message, TaskContext $ctx, string $traceId, array $extra = []): void
    {
        $this->logger->log($level, $message, array_merge([
            'taskId' => $ctx->taskId,
            'traceId' => $traceId,
        ], $extra));
    }
}
