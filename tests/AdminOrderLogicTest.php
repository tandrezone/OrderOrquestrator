<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\Admin\AdminOrderLogic;
use Tandrezone\OrderOrchestrator\Admin\AdminRoutes;
use Tandrezone\OrderOrchestrator\OrderRepository;
use Tandrezone\OrderOrchestrator\OrderStatus;
use Tandrezone\Ztemp\TemplateEngine;

final class AdminOrderLogicTest extends TestCase
{
    public function testRoutesExposeAdministrationEndpoints(): void
    {
        self::assertSame('/admin/orders', AdminRoutes::ordersPath());
        self::assertSame('/admin/orders/10/status', AdminRoutes::updateStatusPath(10));

        $routes = AdminRoutes::definitions();
        self::assertCount(2, $routes);
        self::assertSame('GET', $routes[0]['method']);
        self::assertSame('POST', $routes[1]['method']);
    }

    public function testHandleListFiltersByStatusWhenProvided(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(OrderRepository::class);
        $templateEngine = $this->createMock(TemplateEngine::class);

        $repository->expects(self::never())->method('list');
        $repository->expects(self::once())
            ->method('listByStatus')
            ->with($pdo, OrderStatus::Shipped, 50, 0)
            ->willReturn([[
                'id' => 5,
                'order_number' => 'ORD-20260101-AAAA1111',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'total' => 120.5,
                'status' => 'shipped',
            ]]);

        $templateEngine->expects(self::once())
            ->method('render')
            ->with(
                'admin/orders.html',
                self::callback(static function (array $payload): bool {
                    self::assertSame('shipped', $payload['selected_status']);
                    self::assertSame('/admin/orders/5/status', $payload['orders'][0]['update_status_path']);
                    self::assertSame('120.50', $payload['orders'][0]['total_formatted']);
                    self::assertSame('selected', $payload['filter_options'][4]['selected']);

                    return true;
                })
            )
            ->willReturn('<html>admin</html>');

        $logic = new AdminOrderLogic($repository, $templateEngine);

        $html = $logic->handleList($pdo, ['status' => 'shipped']);

        self::assertSame('<html>admin</html>', $html);
    }

    public function testHandleStatusUpdateRequiresStatusField(): void
    {
        $logic = new AdminOrderLogic(
            $this->createMock(OrderRepository::class),
            $this->createMock(TemplateEngine::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('status field is required');

        $logic->handleStatusUpdate($this->createMock(PDO::class), 3, []);
    }

    public function testHandleStatusUpdatePersistsValidStatus(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(OrderRepository::class);
        $templateEngine = $this->createMock(TemplateEngine::class);

        $repository->expects(self::once())
            ->method('updateStatus')
            ->with($pdo, 9, OrderStatus::Delivered);

        $logic = new AdminOrderLogic($repository, $templateEngine);
        $logic->handleStatusUpdate($pdo, 9, ['status' => 'delivered']);
    }
}
