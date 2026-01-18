<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Psr\Log\LoggerInterface;
use Ruudk\Absurd\Task\Context as TaskContext;

/**
 * Sub-task: Check inventory in a warehouse.
 *
 * Demonstrates:
 * - Quick idempotent operation
 * - Headers/tracing propagation
 */
final readonly class CheckInventoryTask
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array{orderId: string, warehouseId: string, items: list<array{sku: string, qty: int}>} $payload
     * @return array{available: bool, warehouseId: string, reservationId: string|null}
     */
    public function __invoke(array $payload, TaskContext $ctx): array
    {
        $traceId = $ctx->headers['trace_id'] ?? 'none';

        $this->logger->info('Checking inventory in warehouse {warehouseId}', [
            'taskId' => $ctx->taskId,
            'traceId' => $traceId,
            'orderId' => $payload['orderId'],
            'warehouseId' => $payload['warehouseId'],
            'itemCount' => count($payload['items']),
        ]);

        // Simulate 95% availability
        $available = random_int(1, 100) <= 95;

        $reservationId = $available ? 'res_' . bin2hex(random_bytes(8)) : null;

        $this->logger->info('Inventory check complete: {status}', [
            'taskId' => $ctx->taskId,
            'traceId' => $traceId,
            'orderId' => $payload['orderId'],
            'warehouseId' => $payload['warehouseId'],
            'status' => $available ? 'available' : 'unavailable',
            'reservationId' => $reservationId,
        ]);

        return [
            'available' => $available,
            'warehouseId' => $payload['warehouseId'],
            'reservationId' => $reservationId,
        ];
    }
}
