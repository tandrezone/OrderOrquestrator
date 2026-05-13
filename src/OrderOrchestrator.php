<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrquestrator;

use RuntimeException;
use Tandrezone\Ztemp\TemplateEngine;

class OrderOrchestrator
{
    private TemplateEngine $templateEngine;
    private string $shippingFile;

    public function __construct(?TemplateEngine $templateEngine = null, ?string $shippingFile = null, ?string $templateBasePath = null)
    {
        $templateBasePath ??= dirname(__DIR__) . '/resources/templates';
        $this->templateEngine = $templateEngine ?? new TemplateEngine($templateBasePath);
        $this->shippingFile = $shippingFile ?? dirname(__DIR__) . '/resources/shipping-methods.json';
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
