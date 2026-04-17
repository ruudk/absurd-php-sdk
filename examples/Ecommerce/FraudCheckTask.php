<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Task\Context as TaskContext;

/**
 * Sub-task: Perform fraud check.
 *
 * Demonstrates:
 * - Simulated failure (20% chance) to show retry behavior
 * - Event emission when complete
 * - Headers/tracing propagation
 * - Replay-aware logging via $ctx->logger
 */
final readonly class FraudCheckTask
{
    public function __construct(
        private Absurd $absurd,
    ) {}

    /**
     * @param array{orderId: string, customerId: string, totalCents: int, shippingAddress: array<string, string>} $payload
     * @return array{approved: bool, score: float, flags: list<string>}
     */
    public function __invoke(array $payload, TaskContext $ctx): array
    {
        $traceId = $ctx->headers['trace_id'] ?? 'none';

        // taskId and runId are auto-injected by ReplayAwareLogger
        $ctx->logger->info('Starting fraud check for order {orderId}', [
            'traceId' => $traceId,
            'orderId' => $payload['orderId'],
            'customerId' => $payload['customerId'],
            'totalCents' => $payload['totalCents'],
            'attempt' => $ctx->attempt,
        ]);

        // Simulate 20% failure rate to demonstrate retries
        if (random_int(1, 100) <= 20) {
            $ctx->logger->warning('Fraud check service temporarily unavailable', [
                'traceId' => $traceId,
                'orderId' => $payload['orderId'],
                'attempt' => $ctx->attempt,
            ]);
            throw new \RuntimeException('Fraud check service temporarily unavailable');
        }

        // Calculate fraud score
        $score = 0.1;
        if ($payload['totalCents'] > 10000) {
            $score += 0.2;
        }
        if ($payload['totalCents'] > 50000) {
            $score += 0.3;
        }
        $score += (random_int(0, 20) - 10) / 100;
        $score = max(0.0, min(1.0, $score));

        $flags = [];
        if ($payload['totalCents'] > 100000) {
            $flags[] = 'high_value_order';
        }
        if ($score > 0.5) {
            $flags[] = 'elevated_risk';
        }

        $approved = $score < 0.7;

        $result = [
            'approved' => $approved,
            'score' => $score,
            'flags' => $flags,
        ];

        $ctx->logger->info('Fraud check complete: {status} (score: {score})', [
            'traceId' => $traceId,
            'orderId' => $payload['orderId'],
            'status' => $approved ? 'approved' : 'rejected',
            'score' => $score,
            'flags' => implode(', ', $flags),
        ]);

        // Emit result event for main workflow to continue
        $this->absurd->emitEvent(sprintf('fraud-result:%s', $payload['orderId']), $result);

        return $result;
    }
}
