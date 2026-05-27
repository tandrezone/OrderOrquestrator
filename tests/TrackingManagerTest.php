<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\TrackingManager;
use Tandrezone\OrderOrchestrator\TrackingProviderInterface;
use Tandrezone\OrderOrchestrator\TrackingResult;

final class TrackingManagerTest extends TestCase
{
    public function testRegisterNormalizesCarrierCodeAndListsIt(): void
    {
        $manager = new TrackingManager();
        $manager->register(new FakeTrackingProvider('FedEx', 'in_transit'));

        self::assertSame(['fedex'], $manager->registeredCarriers());
    }

    public function testCheckOrderReturnsNullWithoutTrackingData(): void
    {
        $manager = new TrackingManager();
        $manager->register(new FakeTrackingProvider('fedex', 'in_transit'));

        self::assertNull($manager->checkOrder(['id' => 1]));
        self::assertNull($manager->checkOrder([
            'tracking_carrier' => 'fedex',
            'tracking_number' => '',
        ]));
    }

    public function testCheckOrderReturnsNullWhenNoProviderIsRegistered(): void
    {
        $manager = new TrackingManager();

        $result = $manager->checkOrder([
            'tracking_carrier' => 'ups',
            'tracking_number' => 'TRACK-1',
        ]);

        self::assertNull($result);
    }

    public function testCheckOrderUsesRegisteredProvider(): void
    {
        $manager = new TrackingManager();
        $provider = new FakeTrackingProvider('ups', 'delivered', 'Berlin');
        $manager->register($provider);

        $result = $manager->checkOrder([
            'tracking_carrier' => 'UPS',
            'tracking_number' => '1Z999AA10123456784',
        ]);

        self::assertInstanceOf(TrackingResult::class, $result);
        self::assertSame('delivered', $result->status);
        self::assertSame('ups', $result->carrier);
        self::assertSame('1Z999AA10123456784', $result->trackingNumber);
        self::assertSame('Berlin', $result->location);
    }
}

final class FakeTrackingProvider implements TrackingProviderInterface
{
    public function __construct(
        private readonly string $carrierCode,
        private readonly string $status,
        private readonly ?string $location = null,
    ) {}

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function checkDeliveryStatus(string $trackingNumber): TrackingResult
    {
        return new TrackingResult(
            status: $this->status,
            carrier: strtolower($this->carrierCode),
            trackingNumber: $trackingNumber,
            location: $this->location,
        );
    }
}
