<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Controllers;

use RuntimeException;
use Tandrezone\Ztemp\TemplateEngine;

/**
 * Handles GET /order and POST /order.
 *
 * GET  /order — renders the order form with the given product list.
 * POST /order — validates submitted customer data and renders the confirmation view.
 */
class OrderController
{
    private TemplateEngine $templateEngine;

    public function __construct(?TemplateEngine $templateEngine = null)
    {
        $templateBasePath = dirname(__DIR__, 2) . '/resources/templates';
        $this->templateEngine = $templateEngine ?? new TemplateEngine($templateBasePath);
    }

    // -------------------------------------------------------------------------
    // GET /order
    // -------------------------------------------------------------------------

    /**
     * Render the order form.
     *
     * @param array<int, array{id: int, image: string, name: string, price: float, quantity: int}> $products
     *        Products to display in the form. Passed by the application before showing the page.
     * @return string Rendered HTML.
     */
    public function showForm(array $products): string
    {
        [$products, $totalPrice] = $this->enrichProducts($products);

        return $this->templateEngine->render('order.html', [
            'products'      => $products,
            'products_json' => htmlspecialchars(
                json_encode($this->stripEnrichment($products), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ENT_QUOTES,
                'UTF-8'
            ),
            'total_price'   => number_format($totalPrice, 2, '.', ''),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /order
    // -------------------------------------------------------------------------

    /**
     * Validate submitted form data and render the confirmation view.
     *
     * @param array{
     *   customer_name: string,
     *   customer_email: string,
     *   shipping_address: string,
     *   products: string,
     * } $postData Raw POST data from the order form.
     * @return string Rendered HTML.
     */
    public function processForm(array $postData): string
    {
        $customerName    = trim((string) ($postData['customer_name']    ?? ''));
        $customerEmail   = trim((string) ($postData['customer_email']   ?? ''));
        $shippingAddress = trim((string) ($postData['shipping_address'] ?? ''));
        $productsRaw     = (string) ($postData['products'] ?? '[]');

        if ($customerName === '') {
            throw new RuntimeException('Customer name is required.');
        }
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }
        if ($shippingAddress === '') {
            throw new RuntimeException('Shipping address is required.');
        }

        $products = json_decode($productsRaw, true);

        if (!is_array($products) || $products === []) {
            throw new RuntimeException('At least one product is required.');
        }

        [$enriched, $totalPrice] = $this->enrichProducts($products);

        return $this->templateEngine->render('confirmation.html', [
            'customer_name'    => htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
            'customer_email'   => htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8'),
            'shipping_address' => htmlspecialchars($shippingAddress, ENT_QUOTES, 'UTF-8'),
            'products'         => $enriched,
            'total_price'      => number_format($totalPrice, 2, '.', ''),
            'total_price_raw'  => (string) round($totalPrice, 2),
            'products_json'    => htmlspecialchars(
                json_encode($this->stripEnrichment($enriched), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ENT_QUOTES,
                'UTF-8'
            ),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Add display-ready `price_formatted` and `line_total_formatted` keys to every
     * product and compute the overall total.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function enrichProducts(array $products): array
    {
        $total = 0.0;

        foreach ($products as &$product) {
            $price    = round((float) ($product['price']    ?? 0), 2);
            $quantity = max(1, (int) ($product['quantity'] ?? 1));
            $lineTotal = round($price * $quantity, 2);

            $product['price']               = $price;
            $product['quantity']            = $quantity;
            $product['price_formatted']     = number_format($price,    2, '.', '');
            $product['line_total_formatted'] = number_format($lineTotal, 2, '.', '');

            $total += $lineTotal;
        }
        unset($product);

        return [$products, round($total, 2)];
    }

    /**
     * Remove the display-only keys added by enrichProducts() before re-encoding for forms.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function stripEnrichment(array $products): array
    {
        return array_map(static function (array $p): array {
            unset($p['price_formatted'], $p['line_total_formatted']);
            return $p;
        }, $products);
    }
}
