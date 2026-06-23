<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Admin;

use PDO;
use RuntimeException;
use Tandrezone\OrderOrchestrator\OrderRepository;
use Tandrezone\OrderOrchestrator\OrderStatus;
use Tandrezone\Ztemp\TemplateEngine;

final class AdminOrderLogic
{
    private OrderRepository $repository;
    private TemplateEngine $templateEngine;

    public function __construct(
        ?OrderRepository $repository = null,
        ?TemplateEngine $templateEngine = null,
        ?string $templateBasePath = null,
    ) {
        $templateBasePath ??= dirname(__DIR__, 2) . '/resources/templates';
        $this->repository = $repository ?? new OrderRepository();
        $this->templateEngine = $templateEngine ?? new TemplateEngine($templateBasePath);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handleList(PDO $pdo, array $query = [], int $limit = 50, int $offset = 0): string
    {
        $status = $this->parseStatus($query['status'] ?? null);
        $orders = $status === null
            ? $this->repository->list($pdo, $limit, $offset)
            : $this->repository->listByStatus($pdo, $status, $limit, $offset);

        return $this->renderOrders($orders, $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleStatusUpdate(PDO $pdo, int $orderId, array $payload): void
    {
        $status = $this->parseStatus($payload['status'] ?? null);

        if ($status === null) {
            throw new RuntimeException('The status field is required.');
        }

        $this->repository->updateStatus($pdo, $orderId, $status);
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     */
    public function renderOrders(array $orders, ?OrderStatus $selectedStatus = null, ?string $message = null): string
    {
        $statusOptions = $this->buildStatusOptions($selectedStatus);
        $normalizedOrders = [];

        foreach ($orders as $order) {
            $orderStatus = OrderStatus::tryFrom((string) ($order['status'] ?? ''));
            $rowStatusOptions = $this->buildStatusOptions($orderStatus);
            $normalizedOrders[] = [
                'id' => (int) ($order['id'] ?? 0),
                'order_number' => (string) ($order['order_number'] ?? ''),
                'customer_name' => trim((string) (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))),
                'email' => (string) ($order['email'] ?? ''),
                'total_formatted' => number_format((float) ($order['total'] ?? 0), 2, '.', ''),
                'status_label' => $orderStatus?->label() ?? ucfirst((string) ($order['status'] ?? 'unknown')),
                'status_options' => $rowStatusOptions,
                'update_status_path' => AdminRoutes::updateStatusPath((int) ($order['id'] ?? 0)),
            ];
        }

        return $this->templateEngine->render('admin/orders.html', [
            'orders' => $normalizedOrders,
            'filter_options' => $statusOptions,
            'selected_status' => $selectedStatus?->value ?? '',
            'message' => $message ?? '',
            'orders_path' => AdminRoutes::ordersPath(),
        ]);
    }

    private function parseStatus(mixed $value): ?OrderStatus
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $status = OrderStatus::tryFrom($normalized);
        if ($status === null) {
            throw new RuntimeException(sprintf('Invalid order status filter "%s".', $normalized));
        }

        return $status;
    }

    /**
     * @return array<int, array{value:string,label:string,selected:string}>
     */
    private function buildStatusOptions(?OrderStatus $selectedStatus = null): array
    {
        $options = [[
            'value' => '',
            'label' => 'All statuses',
            'selected' => $selectedStatus === null ? 'selected' : '',
        ]];

        foreach (OrderStatus::cases() as $status) {
            $options[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'selected' => $selectedStatus === $status ? 'selected' : '',
            ];
        }

        return $options;
    }
}
