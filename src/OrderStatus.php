<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Paid       = 'paid';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Pending',
            self::Paid       => 'Paid',
            self::Processing => 'Processing',
            self::Shipped    => 'Shipped',
            self::Delivered  => 'Delivered',
            self::Cancelled  => 'Cancelled',
            self::Refunded   => 'Refunded',
        };
    }

    /** Returns true if the order can still be cancelled. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Paid, self::Processing], true);
    }

    /** Returns true if the order is in a terminal state. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Refunded], true);
    }
}
