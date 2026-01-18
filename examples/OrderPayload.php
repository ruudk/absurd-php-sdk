<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples;

use DateTimeImmutable;

/**
 * Typed payload for order fulfillment tasks.
 *
 * Demonstrates typed payloads with automatic (de)serialization.
 */
final readonly class OrderPayload
{
    /**
     * @param list<array{sku: string, qty: int, price: int, warehouse?: string}> $items
     * @param array{street: string, city: string, state: string, zip: string, country: string} $shippingAddress
     */
    public function __construct(
        public string $orderId,
        public string $customerId,
        public string $email,
        public array $items,
        public array $shippingAddress,
        public int $totalCents,
        public string $paymentMethodId,
        public ?DateTimeImmutable $scheduledDelivery = null,
    ) {}
}
