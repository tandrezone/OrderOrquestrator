<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\OrderStatus;

final class OrderStatusTest extends TestCase
{
    public function testLabelReturnsUserFriendlyNames(): void
    {
        self::assertSame('Pending', OrderStatus::Pending->label());
        self::assertSame('Delivered', OrderStatus::Delivered->label());
        self::assertSame('Refunded', OrderStatus::Refunded->label());
    }

    public function testCancellableStatusesAreCorrect(): void
    {
        self::assertTrue(OrderStatus::Pending->isCancellable());
        self::assertTrue(OrderStatus::Paid->isCancellable());
        self::assertTrue(OrderStatus::Processing->isCancellable());

        self::assertFalse(OrderStatus::Shipped->isCancellable());
        self::assertFalse(OrderStatus::Delivered->isCancellable());
    }

    public function testFinalStatusesAreCorrect(): void
    {
        self::assertTrue(OrderStatus::Delivered->isFinal());
        self::assertTrue(OrderStatus::Cancelled->isFinal());
        self::assertTrue(OrderStatus::Refunded->isFinal());

        self::assertFalse(OrderStatus::Pending->isFinal());
        self::assertFalse(OrderStatus::Shipped->isFinal());
    }
}
