# OrderOrchestrator
# OrderOrchestrator

Composer package to create, persist, and track orders from a checkout form.  
Renders an order form template via [`tandrezone/ztemp`](https://github.com/tandrezone/ztemp.git), calculates shipping totals, saves orders to MySQL, and provides a hook system for third-party delivery-tracking integrations.

---

## Installation

```bash
composer require tandrezone/order-orchestrator
```

On install/update the package copies the order form template to `templates/order-form.html`.

### Create the `orders` table

Run the bundled migration once against your database:

```bash
php vendor/bin/create-orders-table
```

Or call it programmatically:

```php
$orchestrator->createOrdersTable($pdo);
```

---

## Usage

### Inspect package structure

```php
use Tandrezone\OrderOrchestrator\OrderOrquestrator;

$package = new OrderOrquestrator();

$metadata = $package->describe();
$entryPoint = $package->entryPointRoute();      // GET /order
$requiredData = $package->requiredEntryPointData();
$ordersSchema = $package->ordersTableSchema();
```

`OrderOrquestrator` is a root-level manifest for the package. It tells you where the templates, routes, migrations, config, scripts, and resources live, which route starts the flow, which input data the entry route requires, and the current `orders` table schema source.

### Render the order form

```php
use Tandrezone\OrderOrchestrator\OrderOrchestrator;

$orchestrator = new OrderOrchestrator();

$products = [
    ['name' => 'Mouse', 'price' => 50],
    ['name' => 'Keyboard', 'price' => 100.75],
];

echo $orchestrator->renderOrderForm($products, 'standard');
$total = $orchestrator->calculateTotal($products, 'express');
```

### Administration routes and logic

Use the admin helpers under `src/admin` to expose an orders administration page:

```php
use Tandrezone\OrderOrchestrator\Admin\AdminOrderLogic;
use Tandrezone\OrderOrchestrator\Admin\AdminRoutes;

$adminLogic = new AdminOrderLogic();
$routes = AdminRoutes::definitions(); // GET /admin/orders, POST /admin/orders/{orderId}/status

// Example list endpoint (status query is optional)
echo $adminLogic->handleList($pdo, ['status' => 'processing']);

// Example update endpoint body: ['status' => 'shipped']
$adminLogic->handleStatusUpdate($pdo, $orderId, $_POST);
```

The package now also ships `resources/templates/admin/orders.html`, copied to `templates/admin/orders.html` on install/update.

### Save an order from checkout

```php
// $pdo — any PDO connected to your database
// $checkoutData — validated POST fields from checkout.html
// $cartItems — decoded cart payload

$orderId = $orchestrator->saveOrder($pdo, [
    'first_name'      => 'Jane',
    'last_name'       => 'Doe',
    'email'           => 'jane@example.com',
    'address'         => '123 Main St',
    'zip'             => '10001',
    'city'            => 'New York',
    'phone'           => '+1 555 000 0000',   // optional
    'shipping_method' => 'standard',
    'payment_gateway' => 'oxo',
], $cartItems);
```

### Update order status

```php
use Tandrezone\OrderOrchestrator\OrderStatus;

$orchestrator->updateOrderStatus($pdo, $orderId, OrderStatus::Paid);
$orchestrator->updateOrderStatus($pdo, $orderId, OrderStatus::Processing);
```

Available statuses: `pending` · `paid` · `processing` · `shipped` · `delivered` · `cancelled` · `refunded`

### Attach tracking information

```php
$orchestrator->updateTracking(
    $pdo,
    $orderId,
    carrier: 'fedex',
    trackingNumber: '123456789012',
    trackingUrl: 'https://www.fedex.com/apps/fedextrack/?trknbr=123456789012',
);
// Status is automatically advanced to 'shipped'.
```

### Integrate a tracking provider

Implement `TrackingProviderInterface` for any carrier:

```php
use Tandrezone\OrderOrchestrator\TrackingProviderInterface;
use Tandrezone\OrderOrchestrator\TrackingResult;

class FedExTrackingProvider implements TrackingProviderInterface
{
    public function getCarrierCode(): string { return 'fedex'; }

    public function checkDeliveryStatus(string $trackingNumber): TrackingResult
    {
        // Call FedEx API …
        return new TrackingResult(
            status: 'in_transit',       // 'in_transit' | 'delivered' | 'exception' | 'unknown'
            carrier: 'fedex',
            trackingNumber: $trackingNumber,
            location: 'Memphis, TN',
            estimatedDelivery: new DateTimeImmutable('2026-06-01'),
            events: [
                ['timestamp' => '2026-05-28 10:00', 'description' => 'Picked up', 'location' => 'New York, NY'],
            ],
        );
    }
}

$orchestrator->registerTrackingProvider(new FedExTrackingProvider());
```

### Check delivery for one order

```php
$result = $orchestrator->checkDelivery($pdo, $orderId);

if ($result?->isDelivered()) {
    echo 'Package delivered!';
}
```

### Batch-check all shipped orders

Ideal for a cron job:

```php
$results = $orchestrator->checkAllDeliveries($pdo);
// Orders confirmed delivered are automatically marked as 'delivered'.
```

---

## `orders` Table Schema

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED | Primary key |
| `order_number` | VARCHAR(50) | Human-readable, e.g. `ORD-20260527-A1B2C3D4` |
| `first_name` / `last_name` | VARCHAR | From checkout form |
| `email` | VARCHAR(255) | |
| `address` / `zip` / `city` | VARCHAR | |
| `phone` | VARCHAR(30) | Optional |
| `shipping_method` | VARCHAR(50) | |
| `shipping_price` | DECIMAL(12,2) | |
| `payment_gateway` | VARCHAR(50) | |
| `subtotal` / `total` | DECIMAL(12,2) | |
| `items` | JSON | Cart snapshot |
| `status` | ENUM | `pending`…`refunded` |
| `tracking_carrier` | VARCHAR(100) | Carrier code, e.g. `fedex` |
| `tracking_number` | VARCHAR(255) | |
| `tracking_url` | VARCHAR(500) | Optional deep-link |
| `tracking_status` | VARCHAR(50) | Last status from provider |
| `tracking_last_checked` | TIMESTAMP | Auto-updated by `checkDelivery()` |
| `notes` | TEXT | Admin notes |
| `created_at` / `updated_at` | TIMESTAMP | |
| `shipped_at` / `delivered_at` | TIMESTAMP | Set automatically on status transitions |
