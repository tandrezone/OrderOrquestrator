<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Admin;

final class AdminRoutes
{
    /**
     * @return array<int, array{method: string, path: string, name: string, handler: array{0: class-string, 1: string}}>
     */
    public static function definitions(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/admin/orders',
                'name' => 'admin.orders.index',
                'handler' => [AdminOrderLogic::class, 'handleList'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/orders/{orderId}/status',
                'name' => 'admin.orders.update-status',
                'handler' => [AdminOrderLogic::class, 'handleStatusUpdate'],
            ],
        ];
    }

    public static function ordersPath(): string
    {
        return '/admin/orders';
    }

    public static function updateStatusPath(int $orderId): string
    {
        return '/admin/orders/' . $orderId . '/status';
    }
}
