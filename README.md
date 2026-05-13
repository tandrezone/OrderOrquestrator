# OrderOrchestrator

Composer package to create orders from a list of products and prices, render an order form template using [`tandrezone/ztemp`](https://github.com/tandrezone/ztemp.git), and calculate totals including shipping.

## Installation

```bash
composer require tandrezone/order-orchestrator
```

On install/update, the package copies the order form template to:

```text
templates/order-form.html
```

## Usage

```php
<?php

require 'vendor/autoload.php';

use Tandrezone\OrderOrchestrator\OrderOrchestrator;

$products = [
    ['name' => 'Mouse', 'price' => 50],
    ['name' => 'Keyboard', 'price' => 100.75],
];

$orderOrchestrator = new OrderOrchestrator();

echo $orderOrchestrator->renderOrderForm($products, 'standard');

$total = $orderOrchestrator->calculateTotal($products, 'express');
```

Shipping options are loaded from `resources/shipping-methods.json` and rendered as a `<select>` in the order form.
