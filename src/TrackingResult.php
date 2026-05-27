<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

/**
 * Immutable value object returned by tracking providers.
 */
final class TrackingResult
{
    /**
     * @param string  $status           Normalised status: 'in_transit'|'delivered'|'exception'|'unknown'
     * @param string  $carrier          Carrier code (e.g. 'ups', 'fedex', 'dhl')
     * @param string  $trackingNumber   Tracking number that was queried
     * @param string|null $location     Latest known location description
     * @param \DateTimeImmutable|null $estimatedDelivery  Carrier-provided ETA, if available
     * @param array<int, array{timestamp: string, description: string, location: string|null}> $events
     *        Chronological list of tracking events (newest first)
     * @param array<string, mixed> $raw  Raw payload from the carrier API, for debugging or extended use
     */
    public function __construct(
        public readonly string $status,
        public readonly string $carrier,
        public readonly string $trackingNumber,
        public readonly ?string $location = null,
        public readonly ?\DateTimeImmutable $estimatedDelivery = null,
        public readonly array $events = [],
        public readonly array $raw = [],
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isException(): bool
    {
        return $this->status === 'exception';
    }
}
