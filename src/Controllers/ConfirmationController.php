<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Controllers;

use PDO;
use RuntimeException;

/**
 * Handles POST /confirmation.
 *
 * Validates the confirmed order data, persists it to the `orders` table, then
 * redirects the browser to the URL defined in config/config.php.
 */
class ConfirmationController
{
    private string $redirectUrl;

    /**
     * @param string|null $redirectUrl
     *   Override the post-order redirect URL. When null (default) the value is
     *   read from config/config.php → 'redirect_after_order'.
     */
    public function __construct(?string $redirectUrl = null)
    {
        if ($redirectUrl !== null) {
            $this->redirectUrl = $redirectUrl;
            return;
        }

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        $config     = is_file($configFile) ? require $configFile : [];

        $this->redirectUrl = is_string($config['redirect_after_order'] ?? null)
            ? $config['redirect_after_order']
            : '/';
    }

    // -------------------------------------------------------------------------
    // POST /confirmation
    // -------------------------------------------------------------------------

    /**
     * Save the confirmed order to the database and redirect.
     *
     * Sends a Location header and exits. If you need to intercept the redirect
     * (e.g. in tests), catch the \RuntimeException thrown on validation errors
     * and call saveOrder() / getRedirectUrl() directly.
     *
     * @param PDO $pdo Active database connection.
     * @param array{
     *   customer_name: string,
     *   customer_email: string,
     *   shipping_address: string,
     *   total_price: string|float,
     *   products: string,
     * } $postData Raw POST data from the confirmation form.
     */
    public function processConfirmation(PDO $pdo, array $postData): void
    {
        $this->saveOrder($pdo, $postData);

        header('Location: ' . $this->redirectUrl);
        exit;
    }

    /**
     * Validate and persist the order without triggering a redirect.
     * Useful for testing or when the caller handles the HTTP response.
     *
     * @param PDO $pdo
     * @param array<string, mixed> $postData
     * @return int The new order's primary key.
     */
    public function saveOrder(PDO $pdo, array $postData): int
    {
        $customerName    = trim((string) ($postData['customer_name']    ?? ''));
        $customerEmail   = trim((string) ($postData['customer_email']   ?? ''));
        $shippingAddress = trim((string) ($postData['shipping_address'] ?? ''));
        $totalPrice      = (float) ($postData['total_price'] ?? 0);
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
        if ($totalPrice <= 0) {
            throw new RuntimeException('Total price must be greater than zero.');
        }

        $products = json_decode($productsRaw, true);

        if (!is_array($products) || $products === []) {
            throw new RuntimeException('At least one product is required.');
        }

        $stmt = $pdo->prepare('
            INSERT INTO orders (customer_name, customer_email, shipping_address, total_price, products)
            VALUES (:customer_name, :customer_email, :shipping_address, :total_price, :products)
        ');

        $stmt->execute([
            ':customer_name'    => $customerName,
            ':customer_email'   => $customerEmail,
            ':shipping_address' => $shippingAddress,
            ':total_price'      => round($totalPrice, 2),
            ':products'         => json_encode(
                $products,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Return the configured post-order redirect URL.
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }
}
