<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\Controllers\ConfirmationController;
use Tandrezone\OrderOrchestrator\Controllers\OrderController;
use Tandrezone\Ztemp\TemplateEngine;

/**
 * End-to-end flow tests covering the complete order lifecycle.
 *
 * The four steps mirror exactly what happens in a real HTTP session:
 *
 *   Step 1 — GET  /order        showForm($products)
 *             Application feeds products into the package entry point.
 *             The rendered payload contains a hidden products_json field.
 *
 *   Step 2 — POST /order        processForm($_POST)
 *             Browser submits customer data + products_json.
 *             The rendered payload is the confirmation page the user reviews.
 *
 *   Step 3 — POST /confirmation saveOrder($pdo, $_POST)
 *             Browser confirms; the package saves the order to the database
 *             and returns the new order ID.
 *
 *   Step 4 — GET  <redirect>
 *             The browser is sent to the configured redirect URL with
 *             total_price appended as a query parameter.
 */
final class OrderFlowTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // SQLite-compatible subset of migrations/CreateOrdersTable.php
        $this->pdo->exec("
            CREATE TABLE orders (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name    TEXT    NOT NULL,
                customer_email   TEXT    NOT NULL,
                shipping_address TEXT    NOT NULL,
                total_price      REAL    NOT NULL,
                products         TEXT    NOT NULL
            )
        ");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an OrderController that captures every render() call.
     *
     * @param array<string, array<string, mixed>> $captured Populated by reference.
     */
    private function orderControllerCapturing(array &$captured): OrderController
    {
        $engine = $this->createMock(TemplateEngine::class);
        $engine->method('render')
            ->willReturnCallback(static function (string $tpl, array $params) use (&$captured): string {
                $captured[$tpl] = $params;
                return "<html>{$tpl}</html>";
            });

        return new OrderController($engine);
    }

    // =========================================================================
    // Step 1 — GET /order: showForm()
    // =========================================================================

    public function testStep1ShowFormProducesOrderHtmlWithEnrichedProducts(): void
    {
        $captured = [];
        $this->orderControllerCapturing($captured)->showForm([
            ['id' => 1, 'image' => '/img/shoe.png', 'name' => 'Running Shoe', 'price' => 49.99, 'quantity' => 2],
            ['id' => 2, 'image' => '/img/sock.png', 'name' => 'Sports Sock',  'price' =>  4.99, 'quantity' => 4],
        ]);

        $payload = $captured['order.html'];

        // Total: 49.99 × 2 + 4.99 × 4 = 99.98 + 19.96 = 119.94
        self::assertSame('119.94', $payload['total_price']);
        self::assertSame('99.98',  $payload['products'][0]['line_total_formatted']);
        self::assertSame('19.96',  $payload['products'][1]['line_total_formatted']);
    }

    public function testStep1ProductsJsonContainsOriginalFieldsOnly(): void
    {
        $captured = [];
        $this->orderControllerCapturing($captured)->showForm([
            ['id' => 1, 'image' => '/img/shoe.png', 'name' => 'Running Shoe', 'price' => 49.99, 'quantity' => 2],
        ]);

        $json    = html_entity_decode($captured['order.html']['products_json']);
        $decoded = json_decode($json, true);

        // Display-only keys must be stripped so the form hidden field stays lean
        self::assertArrayNotHasKey('price_formatted',      $decoded[0]);
        self::assertArrayNotHasKey('line_total_formatted', $decoded[0]);

        // Original fields must be intact
        self::assertSame(1,             $decoded[0]['id']);
        self::assertSame('Running Shoe', $decoded[0]['name']);
        self::assertEquals(49.99,       $decoded[0]['price']);
    }

    // =========================================================================
    // Step 2 — POST /order: processForm()
    // =========================================================================

    public function testStep2ProcessFormProducesConfirmationHtmlWithCorrectData(): void
    {
        // Simulate the hidden products_json field value that step 1 embedded in the form
        $productsJson = json_encode([
            ['id' => 1, 'image' => '/img/shoe.png', 'name' => 'Running Shoe', 'price' => 49.99, 'quantity' => 2],
            ['id' => 2, 'image' => '/img/sock.png', 'name' => 'Sports Sock',  'price' =>  4.99, 'quantity' => 4],
        ]);

        $captured = [];
        $this->orderControllerCapturing($captured)->processForm([
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'shipping_address' => '123 Main St',
            'products'         => $productsJson,
        ]);

        $payload = $captured['confirmation.html'];

        self::assertStringContainsString('Jane Doe',         $payload['customer_name']);
        self::assertStringContainsString('jane@example.com', $payload['customer_email']);
        self::assertStringContainsString('123 Main St',      $payload['shipping_address']);
        self::assertSame('119.94', $payload['total_price']);
        self::assertSame('119.94', $payload['total_price_raw']);
        self::assertCount(2, $payload['products']);
    }

    public function testStep2ConfirmationPayloadContainsProductsJson(): void
    {
        $captured = [];
        $this->orderControllerCapturing($captured)->processForm([
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'shipping_address' => '123 Main St',
            'products'         => json_encode([
                ['id' => 5, 'name' => 'Hat', 'price' => 20.00, 'quantity' => 1],
            ]),
        ]);

        $json    = html_entity_decode($captured['confirmation.html']['products_json']);
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame(5,     $decoded[0]['id']);
        self::assertSame('Hat', $decoded[0]['name']);
    }

    // =========================================================================
    // Step 3 — POST /confirmation: saveOrder()
    // =========================================================================

    public function testStep3SaveOrderPersistsAllFieldsAndReturnsId(): void
    {
        $controller = new ConfirmationController('/thank-you');

        $id = $controller->saveOrder($this->pdo, [
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'shipping_address' => '123 Main St',
            'total_price'      => '119.94',
            'products'         => json_encode([
                ['id' => 1, 'name' => 'Running Shoe', 'price' => 49.99, 'quantity' => 2],
                ['id' => 2, 'name' => 'Sports Sock',  'price' =>  4.99, 'quantity' => 4],
            ]),
        ]);

        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);

        $row = $this->pdo->query("SELECT * FROM orders WHERE id = {$id}")->fetch();

        self::assertSame('Jane Doe',          $row['customer_name']);
        self::assertSame('jane@example.com',  $row['customer_email']);
        self::assertSame('123 Main St',       $row['shipping_address']);
        self::assertEquals(119.94,            (float) $row['total_price']);

        $products = json_decode($row['products'], true);
        self::assertCount(2, $products);
        self::assertSame('Running Shoe', $products[0]['name']);
    }

    // =========================================================================
    // Step 4 — Redirect URL carries total_price
    // =========================================================================

    public function testStep4RedirectUrlContainsTotalPrice(): void
    {
        $controller  = new ConfirmationController('/thank-you');
        $base        = $controller->getRedirectUrl();
        $totalPrice  = '119.94';
        $sep         = str_contains($base, '?') ? '&' : '?';
        $finalUrl    = $base . $sep . 'total_price=' . urlencode($totalPrice);

        self::assertStringStartsWith('/thank-you', $finalUrl);
        self::assertStringContainsString('total_price=119.94', $finalUrl);
    }

    // =========================================================================
    // Full pipeline — products in through redirect URL out
    // =========================================================================

    /**
     * Simulates a complete browser session:
     *   1. App calls showForm() with a product catalogue.
     *   2. User fills in personal data and submits; processForm() renders confirmation.
     *   3. User confirms; saveOrder() writes to the database.
     *   4. The redirect URL carries total_price so the host page can display it.
     */
    public function testFullPipelineFromProductsToRedirectUrl(): void
    {
        // ── Step 1 ────────────────────────────────────────────────────────────
        $step1 = [];
        $this->orderControllerCapturing($step1)->showForm([
            ['id' => 10, 'image' => '/img/hat.png', 'name' => 'Hat', 'price' => 19.99, 'quantity' => 1],
        ]);

        $productsJsonFromForm = html_entity_decode($step1['order.html']['products_json']);

        // ── Step 2 ────────────────────────────────────────────────────────────
        $step2 = [];
        $this->orderControllerCapturing($step2)->processForm([
            'customer_name'    => 'Bob',
            'customer_email'   => 'bob@example.com',
            'shipping_address' => '99 Pine Rd',
            'products'         => $productsJsonFromForm,
        ]);

        $confirmPayload = $step2['confirmation.html'];

        // ── Step 3 ────────────────────────────────────────────────────────────
        $controller = new ConfirmationController('/thank-you');

        $id = $controller->saveOrder($this->pdo, [
            'customer_name'    => html_entity_decode($confirmPayload['customer_name']),
            'customer_email'   => html_entity_decode($confirmPayload['customer_email']),
            'shipping_address' => html_entity_decode($confirmPayload['shipping_address']),
            'total_price'      => $confirmPayload['total_price_raw'],
            'products'         => html_entity_decode($confirmPayload['products_json']),
        ]);

        $row = $this->pdo->query("SELECT * FROM orders WHERE id = {$id}")->fetch();

        self::assertSame('Bob',              $row['customer_name']);
        self::assertSame('bob@example.com',  $row['customer_email']);
        self::assertSame('99 Pine Rd',       $row['shipping_address']);
        self::assertEquals(19.99,            (float) $row['total_price']);

        $savedProducts = json_decode($row['products'], true);
        self::assertSame('Hat', $savedProducts[0]['name']);

        // ── Step 4 ────────────────────────────────────────────────────────────
        $base     = $controller->getRedirectUrl();
        $sep      = str_contains($base, '?') ? '&' : '?';
        $finalUrl = $base . $sep . 'total_price=' . urlencode($confirmPayload['total_price_raw']);

        self::assertSame('/thank-you?total_price=19.99', $finalUrl);
    }

    /**
     * Verifies that the total calculated in step 1 (showForm) exactly matches
     * the total stored in the database after step 3 (saveOrder).
     *
     * This guards against drift between the display total and the persisted total.
     */
    public function testTotalPriceIsConsistentFromShowFormToDatabase(): void
    {
        // Step 1
        $step1 = [];
        $this->orderControllerCapturing($step1)->showForm([
            ['id' => 1, 'image' => '', 'name' => 'A', 'price' => 12.50, 'quantity' => 3], // 37.50
            ['id' => 2, 'image' => '', 'name' => 'B', 'price' =>  7.25, 'quantity' => 2], // 14.50
        ]);

        $totalFromStep1 = $step1['order.html']['total_price']; // '52.00'

        // Step 2
        $step2 = [];
        $this->orderControllerCapturing($step2)->processForm([
            'customer_name'    => 'Carol',
            'customer_email'   => 'carol@example.com',
            'shipping_address' => '5 Elm St',
            'products'         => html_entity_decode($step1['order.html']['products_json']),
        ]);

        $totalFromStep2 = $step2['confirmation.html']['total_price_raw'];

        // Step 3
        $controller = new ConfirmationController('/receipt');
        $id = $controller->saveOrder($this->pdo, [
            'customer_name'    => 'Carol',
            'customer_email'   => 'carol@example.com',
            'shipping_address' => '5 Elm St',
            'total_price'      => $totalFromStep2,
            'products'         => html_entity_decode($step2['confirmation.html']['products_json']),
        ]);

        $row = $this->pdo->query("SELECT total_price FROM orders WHERE id = {$id}")->fetch();

        self::assertSame($totalFromStep1, $totalFromStep2);
        self::assertEquals((float) $totalFromStep1, (float) $row['total_price']);
    }
}
