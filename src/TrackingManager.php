<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

use PDO;
use RuntimeException;

/**
 * Manages registered tracking providers and dispatches delivery-status checks.
 */
final class TrackingManager
{
    /** @var array<string, TrackingProviderInterface> keyed by carrier code */
    private array $providers = [];

    public function register(TrackingProviderInterface $provider): void
    {
        $this->providers[strtolower($provider->getCarrierCode())] = $provider;
    }

    /**
     * Returns all registered provider carrier codes.
     *
     * @return string[]
     */
    public function registeredCarriers(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check the delivery status of a single order.
     *
     * The order row must have `tracking_carrier` and `tracking_number` set.
     * Returns null when no matching provider is registered or tracking data is missing.
     *
     * @param array<string, mixed> $order  A row from OrderRepository::find()
     */
    public function checkOrder(array $order): ?TrackingResult
    {
        $carrier = strtolower((string) ($order['tracking_carrier'] ?? ''));
        $trackingNumber = (string) ($order['tracking_number'] ?? '');

        if ($carrier === '' || $trackingNumber === '') {
            return null;
        }

        if (!isset($this->providers[$carrier])) {
            return null;
        }

        return $this->providers[$carrier]->checkDeliveryStatus($trackingNumber);
    }

    /**
     * Iterate all shipped orders with tracking info and run a delivery check
     * via the registered provider for each carrier.
     *
     * @param PDO $pdo
     * @param OrderRepository $repository
     * @return array<int, array{order_id: int, result: TrackingResult}>
     */
    public function checkAllShipped(PDO $pdo, OrderRepository $repository): array
    {
        $results = [];

        foreach ($repository->findPendingShipments($pdo) as $order) {
            $result = $this->checkOrder($order);

            if ($result === null) {
                continue;
            }

            $orderId = (int) $order['id'];

            // Auto-update order status when the carrier confirms delivery.
            if ($result->isDelivered()) {
                $repository->updateStatus($pdo, $orderId, OrderStatus::Delivered);
            }

            // Persist the last-checked timestamp and tracking status.
            $repository->updateTrackingStatus($pdo, $orderId, $result->status);

            $results[] = ['order_id' => $orderId, 'result' => $result];
        }

        return $results;
    }
}
