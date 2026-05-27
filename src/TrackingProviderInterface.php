<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

/**
 * Contract that every third-party tracking integration must implement.
 *
 * Example usage
 * -------------
 *   class FedExTrackingProvider implements TrackingProviderInterface
 *   {
 *       public function getCarrierCode(): string { return 'fedex'; }
 *
 *       public function checkDeliveryStatus(string $trackingNumber): TrackingResult
 *       {
 *           // call FedEx API …
 *           return new TrackingResult(
 *               status: 'in_transit',
 *               carrier: $this->getCarrierCode(),
 *               trackingNumber: $trackingNumber,
 *               location: 'Memphis, TN',
 *           );
 *       }
 *   }
 *
 *   $orchestrator->registerTrackingProvider(new FedExTrackingProvider());
 */
interface TrackingProviderInterface
{
    /**
     * Unique carrier identifier (lowercase, no spaces).
     * Must match the value stored in the orders.tracking_carrier column.
     */
    public function getCarrierCode(): string;

    /**
     * Query the carrier API and return a normalised result.
     *
     * Implementations MUST NOT throw; they should return a TrackingResult
     * with status 'unknown' when the query fails.
     */
    public function checkDeliveryStatus(string $trackingNumber): TrackingResult;
}
