<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\TrackingResult;

final class TrackingResultTest extends TestCase
{
    public function testIsDeliveredReturnsTrueOnlyForDeliveredStatus(): void
    {
        $delivered = new TrackingResult('delivered', 'fedex', 'ABC123');
        $inTransit = new TrackingResult('in_transit', 'fedex', 'ABC123');

        self::assertTrue($delivered->isDelivered());
        self::assertFalse($inTransit->isDelivered());
    }

    public function testIsExceptionReturnsTrueOnlyForExceptionStatus(): void
    {
        $exception = new TrackingResult('exception', 'dhl', 'ZX9');
        $unknown = new TrackingResult('unknown', 'dhl', 'ZX9');

        self::assertTrue($exception->isException());
        self::assertFalse($unknown->isException());
    }
}
