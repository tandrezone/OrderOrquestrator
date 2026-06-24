# OrderOrchestrator

Composer package to render an order form, capture customer and cart data, and persist orders to MySQL.  
Renders templates via [`tandrezone/ztemp`](https://github.com/tandrezone/ztemp.git).

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

`OrderOrquestrator` is a root-level manifest for the package. It tells you where the templates, routes, migrations, config, scripts, and resources live, which route starts the order flow, which input data the entry route requires, and the current `orders` table schema.

---

## `orders` Table Schema

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `customer_name` | VARCHAR(255) | From checkout form |
| `customer_email` | VARCHAR(255) | |
| `shipping_address` | VARCHAR(500) | |
| `total_price` | DECIMAL(12,2) | |
| `products` | JSON | Cart snapshot |
| `created_at` / `updated_at` | TIMESTAMP | Auto-managed |
