<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

use PDO;
use RuntimeException;
use Tandrezone\Ztemp\TemplateEngine;

class OrderOrchestrator
{
    private TemplateEngine $templateEngine;
    private string $shippingFile;
    private OrderRepository $repository;
    private TrackingManager $trackingManager;

    public function __construct(
        ?TemplateEngine $templateEngine = null,
        ?string $shippingFile = null,
        ?string $templateBasePath = null,
        ?OrderRepository $repository = null,
        ?TrackingManager $trackingManager = null,
    ) {
        $templateBasePath ??= dirname(__DIR__) . '/resources/templates';
        $this->templateEngine   = $templateEngine   ?? new TemplateEngine($templateBasePath);
        $this->shippingFile     = $shippingFile     ?? dirname(__DIR__) . '/resources/shipping-methods.json';
        $this->repository       = $repository       ?? new OrderRepository();
        $this->trackingManager  = $trackingManager  ?? new TrackingManager();
    }

    // -------------------------------------------------------------------------
    // Tracking provider registration
    // -------------------------------------------------------------------------

    /**
     * Register a carrier tracking integration.
     * The carrier code must match the value stored in orders.tracking_carrier.
     */
    public function registerTrackingProvider(TrackingProviderInterface $provider): void
    {
        $this->trackingManager->register($provider);
    }

    // -------------------------------------------------------------------------
    // Order persistence
    // -------------------------------------------------------------------------

    /**
     * Validate and persist an order coming from checkout.html.
     *
     * @param PDO $pdo  Active database connection.
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   address: string,
     *   zip: string,
     *   city: string,
     *   phone?: string|null,
     *   shipping_method: string,
     *   payment_gateway: string,
     * } $checkoutData  Validated POST data from the checkout form.
     * @param array<int, array{name: string, price: numeric-string|int|float, quantity?: int}> $cartItems
     * @return int  The new order's primary key.
     */
    public function saveOrder(PDO $pdo, array $checkoutData, array $cartItems): int
    {
        $this->assertRequiredCheckoutFields($checkoutData);

        $subtotal      = $this->calculateSubtotal($cartItems);
        $shippingPrice = $this->resolveShippingPrice((string) $checkoutData['shipping_method']);
        $total         = round($subtotal + $shippingPrice, 2);

        return $this->repository->save($pdo, [
            'first_name'      => $checkoutData['first_name'],
            'last_name'       => $checkoutData['last_name'],
            'email'           => $checkoutData['email'],
            'address'         => $checkoutData['address'],
            'zip'             => $checkoutData['zip'],
            'city'            => $checkoutData['city'],
            'phone'           => $checkoutData['phone'] ?? null,
            'shipping_method' => $checkoutData['shipping_method'],
            'shipping_price'  => $shippingPrice,
            'payment_gateway' => $checkoutData['payment_gateway'],
            'subtotal'        => $subtotal,
            'total'           => $total,
            'items'           => $cartItems,
        ]);
    }

    /**
     * Change the status of an order.
     */
    public function updateOrderStatus(PDO $pdo, int $orderId, OrderStatus $status): void
    {
        $this->repository->updateStatus($pdo, $orderId, $status);
    }

    /**
     * Attach carrier tracking info to an order (advances status to 'shipped').
     */
    public function updateTracking(
        PDO $pdo,
        int $orderId,
        string $carrier,
        string $trackingNumber,
        ?string $trackingUrl = null,
    ): void {
        $this->repository->updateTracking($pdo, $orderId, $carrier, $trackingNumber, $trackingUrl);
    }

    // -------------------------------------------------------------------------
    // Delivery tracking
    // -------------------------------------------------------------------------

    /**
     * Check the delivery status of a single order via its registered carrier provider.
     * Returns null if the order has no tracking info or no matching provider is registered.
     */
    public function checkDelivery(PDO $pdo, int $orderId): ?TrackingResult
    {
        $order = $this->repository->find($pdo, $orderId);

        if ($order === null) {
            throw new RuntimeException("Order #{$orderId} not found.");
        }

        $result = $this->trackingManager->checkOrder($order);

        if ($result !== null) {
            if ($result->isDelivered()) {
                $this->repository->updateStatus($pdo, $orderId, OrderStatus::Delivered);
            }
            $this->repository->updateTrackingStatus($pdo, $orderId, $result->status);
        }

        return $result;
    }

    /**
     * Run delivery checks for every shipped order that has tracking info.
     * Automatically marks orders as delivered when the carrier confirms it.
     *
     * @return array<int, array{order_id: int, result: TrackingResult}>
     */
    public function checkAllDeliveries(PDO $pdo): array
    {
        return $this->trackingManager->checkAllShipped($pdo, $this->repository);
    }

    // -------------------------------------------------------------------------
    // Order retrieval helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findOrder(PDO $pdo, int $orderId): ?array
    {
        return $this->repository->find($pdo, $orderId);
    }

    /** @return array<string, mixed>|null */
    public function findOrderByNumber(PDO $pdo, string $orderNumber): ?array
    {
        return $this->repository->findByOrderNumber($pdo, $orderNumber);
    }

    /** @return array<int, array<string, mixed>> */
    public function listOrders(PDO $pdo, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->list($pdo, $limit, $offset);
    }

    /** @return array<int, array<string, mixed>> */
    public function listOrdersByStatus(PDO $pdo, OrderStatus $status, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->listByStatus($pdo, $status, $limit, $offset);
    }

    // -------------------------------------------------------------------------
    // Schema setup
    // -------------------------------------------------------------------------

    /**
     * Create the orders table. Safe to call multiple times (idempotent).
     * Typically invoked from a one-off migration or setup command.
     */
    public function createOrdersTable(PDO $pdo): void
    {
        $this->repository->createTable($pdo);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function assertRequiredCheckoutFields(array $data): void
    {
        $required = ['first_name', 'last_name', 'email', 'address', 'zip', 'city', 'shipping_method', 'payment_gateway'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new RuntimeException("Missing required checkout field: {$field}");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address provided.');
        }
    }

    private function resolveShippingPrice(string $shippingCode): float
    {
        foreach ($this->loadShippingOptions() as $option) {
            if ($option['code'] === $shippingCode) {
                return (float) $option['price'];
            }
        }

        return 0.0;
    }

    /**
     * @param array<int, array{name:string, price:numeric-string|int|float}> $products
     */
    public function calculateSubtotal(array $products): float
    {
        $subtotal = 0.0;

        foreach ($products as $product) {
            $subtotal += (float) ($product['price'] ?? 0);
        }

        return round($subtotal, 2);
    }

    /**
     * @return array<int, array{code:string,label:string,price:float,price_formatted:string}>
     */
    public function loadShippingOptions(): array
    {
        if (!is_file($this->shippingFile)) {
            throw new RuntimeException(sprintf('Shipping file not found: %s', $this->shippingFile));
        }

        $shippingFileContent = file_get_contents($this->shippingFile);

        if ($shippingFileContent === false) {
            throw new RuntimeException(sprintf('Unable to read shipping file: %s', $this->shippingFile));
        }

        $decoded = json_decode($shippingFileContent, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid shipping JSON format.');
        }

        $options = [];

        foreach ($decoded as $option) {
            if (!is_array($option) || !isset($option['code'], $option['label'], $option['price'])) {
                continue;
            }

            $price = round((float) $option['price'], 2);
            $options[] = [
                'code' => (string) $option['code'],
                'label' => (string) $option['label'],
                'price' => $price,
                'price_formatted' => number_format($price, 2, '.', ''),
            ];
        }

        return $options;
    }

    /**
     * @param array<int, array{name:string, price:numeric-string|int|float}> $products
     */
    public function calculateTotal(array $products, string $shippingCode): float
    {
        $subtotal = $this->calculateSubtotal($products);
        $shippingPrice = 0.0;

        foreach ($this->loadShippingOptions() as $option) {
            if ($option['code'] === $shippingCode) {
                $shippingPrice = (float) $option['price'];
                break;
            }
        }

        return round($subtotal + $shippingPrice, 2);
    }

    /**
     * @param array<int, array{name:string, price:numeric-string|int|float}> $products
     */
    public function renderOrderForm(array $products, ?string $selectedShippingCode = null): string
    {
        $shippingOptions = $this->loadShippingOptions();

        if ($shippingOptions === []) {
            throw new RuntimeException('At least one shipping option is required.');
        }

        $selectedShippingCode ??= $shippingOptions[0]['code'];
        $subtotal = $this->calculateSubtotal($products);
        $selectedShippingPrice = 0.0;

        foreach ($shippingOptions as &$shippingOption) {
            $shippingOption['selected'] = $shippingOption['code'] === $selectedShippingCode ? 'selected' : '';

            if ($shippingOption['selected'] === 'selected') {
                $selectedShippingPrice = (float) $shippingOption['price'];
            }
        }
        unset($shippingOption);

        foreach ($products as &$product) {
            $price = round((float) ($product['price'] ?? 0), 2);
            $product['price'] = $price;
            $product['price_formatted'] = number_format($price, 2, '.', '');
        }
        unset($product);

        $total = round($subtotal + $selectedShippingPrice, 2);

        return $this->templateEngine->render('order-form.html', [
            'products' => $products,
            'shippingOptions' => $shippingOptions,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ]);
    }
}
