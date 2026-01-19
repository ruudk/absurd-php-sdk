<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Ecommerce;

use Ruudk\Absurd\Task\Context as TaskContext;

/**
 * Sub-task that sends customer notifications (email + SMS).
 *
 * Demonstrates:
 * - Idempotency key usage (notification:{orderId}:{type})
 * - RetryStrategy::fixed(10) for reliable delivery
 * - Multiple notification channels (email, SMS)
 * - Trace ID propagation from parent task
 * - Replay-aware logging via $ctx->logger
 */
final readonly class SendNotificationTask
{
    /**
     * @param array{orderId: string, email: string, type: 'shipped'|'delivered', trackingNumber: string, carrier: string} $params
     * @return array{sent: bool, channels: list<string>, messageIds: array{email?: string, sms?: string}}
     */
    public function __invoke(array $params, TaskContext $ctx): array
    {
        $traceId = $ctx->headers['trace_id'] ?? 'none';

        // taskId and runId are auto-injected by ReplayAwareLogger
        $ctx->logger->info('Sending {type} notification for order {orderId}', [
            'traceId' => $traceId,
            'orderId' => $params['orderId'],
            'type' => $params['type'],
            'email' => $params['email'],
            'attempt' => $ctx->attempt,
        ]);

        $channels = [];
        $messageIds = [];

        // Send email notification
        $emailMessageId = $this->sendEmail($params, $ctx, $traceId);
        if ($emailMessageId !== null) {
            $channels[] = 'email';
            $messageIds['email'] = $emailMessageId;
        }

        // Send SMS notification (simulate phone lookup from customer profile)
        $smsMessageId = $this->sendSms($params, $ctx, $traceId);
        if ($smsMessageId !== null) {
            $channels[] = 'sms';
            $messageIds['sms'] = $smsMessageId;
        }

        $ctx->logger->info('Notification sent for order {orderId} via {channels}', [
            'traceId' => $traceId,
            'orderId' => $params['orderId'],
            'channels' => implode(', ', $channels),
            'messageIds' => $messageIds,
        ]);

        return [
            'sent' => $channels !== [],
            'channels' => $channels,
            'messageIds' => $messageIds,
        ];
    }

    /**
     * @param array{orderId: string, email: string, type: 'shipped'|'delivered', trackingNumber: string, carrier: string} $params
     */
    private function sendEmail(array $params, TaskContext $ctx, string $traceId): ?string
    {
        // Simulate email sending
        $ctx->logger->debug('Sending email to {email}', [
            'traceId' => $traceId,
            'email' => $params['email'],
        ]);

        $subject = match ($params['type']) {
            'shipped' => sprintf('Your order %s has shipped!', $params['orderId']),
            'delivered' => sprintf('Your order %s has been delivered!', $params['orderId']),
        };

        $body = match ($params['type']) {
            'shipped' => sprintf(
                "Great news! Your order is on its way.\n\nTracking: %s\nCarrier: %s\n\nTrack your package at: https://track.example.com/%s",
                $params['trackingNumber'],
                $params['carrier'],
                $params['trackingNumber'],
            ),
            'delivered' => sprintf(
                "Your order has been delivered!\n\nTracking: %s\n\nThank you for shopping with us.",
                $params['trackingNumber'],
            ),
        };

        // Simulate email API response
        $messageId = 'email_' . bin2hex(random_bytes(12));

        $ctx->logger->debug('Email sent: {subject}', [
            'traceId' => $traceId,
            'subject' => $subject,
            'messageId' => $messageId,
            'bodyLength' => strlen($body),
        ]);

        return $messageId;
    }

    /**
     * @param array{orderId: string, email: string, type: 'shipped'|'delivered', trackingNumber: string, carrier: string} $params
     */
    private function sendSms(array $params, TaskContext $ctx, string $traceId): ?string
    {
        // Simulate phone number lookup - 70% of customers have SMS enabled
        if (random_int(1, 100) > 70) {
            $ctx->logger->debug('Customer has no phone number on file, skipping SMS', [
                'traceId' => $traceId,
                'orderId' => $params['orderId'],
            ]);
            return null;
        }

        $message = match ($params['type']) {
            'shipped' => sprintf('Your order %s shipped! Track: %s', $params['orderId'], $params['trackingNumber']),
            'delivered' => sprintf(
                'Your order %s delivered! Tracking: %s',
                $params['orderId'],
                $params['trackingNumber'],
            ),
        };

        // Simulate SMS API response
        $messageId = 'sms_' . bin2hex(random_bytes(8));

        $ctx->logger->debug('SMS sent for order {orderId}', [
            'traceId' => $traceId,
            'orderId' => $params['orderId'],
            'messageId' => $messageId,
            'messageLength' => strlen($message),
        ]);

        return $messageId;
    }
}
