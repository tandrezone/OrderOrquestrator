<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tandrezone\OrderOrchestrator\Controllers\ConfirmationController;

/**
 * Unit tests for ConfirmationController (POST /confirmation).
 *
 * An in-memory SQLite database is used so no real MySQL server is required.
 * The schema is a SQLite-compatible subset of the table created by
 * migrations/CreateOrdersTable.php.
 */
final class ConfirmationControllerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

    private function controller(string $redirect = '/thank-you'): ConfirmationController
    {
        return new ConfirmationController($redirect);
    }

    /** Minimal valid POST payload for saveOrder(). */
    private function validPost(array $overrides = []): array
    {
        return array_merge([
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'shipping_address' => '123 Main St',
            'total_price'      => 19.99,
            'products'         => json_encode([
                ['id' => 1, 'name' => 'Widget', 'price' => 9.99, 'quantity' => 2],
            ]),
        ], $overrides);
    }

    // =========================================================================
    // getRedirectUrl
    // =========================================================================

    public function testGetRedirectUrlReturnsConstructorValue(): void
    {
        self::assertSame('/thank-you', $this->controller('/thank-you')->getRedirectUrl());
    }

    public function testGetRedirectUrlReturnsAbsoluteUrl(): void
    {
        self::assertSame(
            'https://example.com/thank-you',
            $this->controller('https://example.com/thank-you')->getRedirectUrl()
        );
    }

    // =========================================================================
    // saveOrder — happy path
    // =========================================================================

    public function testSaveOrderReturnsPositiveIntegerId(): void
    {
        $id = $this->controller()->saveOrder($this->pdo, $this->validPost());

        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);
    }

    public function testSaveOrderPersistsCustomerName(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_name' => 'Alice Smith']));
        $row = $this->pdo->query("SELECT customer_name FROM orders WHERE id = {$id}")->fetch();

        self::assertSame('Alice Smith', $row['customer_name']);
    }

    public function testSaveOrderPersistsCustomerEmail(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_email' => 'alice@example.com']));
        $row = $this->pdo->query("SELECT customer_email FROM orders WHERE id = {$id}")->fetch();

        self::assertSame('alice@example.com', $row['customer_email']);
    }

    public function testSaveOrderPersistsShippingAddress(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['shipping_address' => '99 Oak Ave']));
        $row = $this->pdo->query("SELECT shipping_address FROM orders WHERE id = {$id}")->fetch();

        self::assertSame('99 Oak Ave', $row['shipping_address']);
    }

    public function testSaveOrderPersistsTotalPrice(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => 49.95]));
        $row = $this->pdo->query("SELECT total_price FROM orders WHERE id = {$id}")->fetch();

        self::assertEquals(49.95, (float) $row['total_price']);
    }

    public function testSaveOrderPersistsProductsAsDecodableJson(): void
    {
        $products = [['id' => 7, 'name' => 'Gadget', 'price' => 12.00, 'quantity' => 3]];
        $id       = $this->controller()->saveOrder($this->pdo, $this->validPost([
            'products' => json_encode($products),
        ]));
        $row = $this->pdo->query("SELECT products FROM orders WHERE id = {$id}")->fetch();

        $decoded = json_decode($row['products'], true);
        self::assertSame(7,        $decoded[0]['id']);
        self::assertSame('Gadget', $decoded[0]['name']);
        self::assertSame(3,        $decoded[0]['quantity']);
    }

    public function testSaveOrderReturnsIncrementingIds(): void
    {
        $id1 = $this->controller()->saveOrder($this->pdo, $this->validPost());
        $id2 = $this->controller()->saveOrder($this->pdo, $this->validPost());

        self::assertGreaterThan($id1, $id2);
    }

    public function testSaveOrderWithMultipleProductsPersistsAll(): void
    {
        $products = [
            ['id' => 1, 'name' => 'A', 'price' => 5.00, 'quantity' => 1],
            ['id' => 2, 'name' => 'B', 'price' => 3.00, 'quantity' => 2],
        ];
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['products' => json_encode($products)]));
        $row = $this->pdo->query("SELECT products FROM orders WHERE id = {$id}")->fetch();

        self::assertCount(2, json_decode($row['products'], true));
    }

    // =========================================================================
    // saveOrder — total_price coercion / rounding
    // =========================================================================

    public function testSaveOrderAcceptsTotalPriceAsString(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => '14.50']));
        $row = $this->pdo->query("SELECT total_price FROM orders WHERE id = {$id}")->fetch();

        self::assertEquals(14.50, (float) $row['total_price']);
    }

    public function testSaveOrderRoundsTotalPriceToTwoDecimalPlaces(): void
    {
        $id  = $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => 9.999]));
        $row = $this->pdo->query("SELECT total_price FROM orders WHERE id = {$id}")->fetch();

        self::assertEquals(10.00, (float) $row['total_price']);
    }

    // =========================================================================
    // saveOrder — validation errors
    // =========================================================================

    public function testSaveOrderThrowsWhenCustomerNameIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer name is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_name' => '']));
    }

    public function testSaveOrderThrowsWhenCustomerNameIsOnlyWhitespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer name is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_name' => '   ']));
    }

    public function testSaveOrderThrowsForEmailMissingAtSymbol(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A valid email address is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_email' => 'not-valid']));
    }

    public function testSaveOrderThrowsForEmptyEmail(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A valid email address is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_email' => '']));
    }

    public function testSaveOrderThrowsForEmailWithoutDomain(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A valid email address is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['customer_email' => 'user@']));
    }

    public function testSaveOrderThrowsWhenShippingAddressIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shipping address is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['shipping_address' => '']));
    }

    public function testSaveOrderThrowsWhenShippingAddressIsOnlyWhitespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shipping address is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['shipping_address' => '   ']));
    }

    public function testSaveOrderThrowsWhenTotalPriceIsZero(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Total price must be greater than zero.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => 0]));
    }

    public function testSaveOrderThrowsWhenTotalPriceIsNegative(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Total price must be greater than zero.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => -5.00]));
    }

    public function testSaveOrderThrowsWhenTotalPriceIsZeroString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Total price must be greater than zero.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['total_price' => '0']));
    }

    public function testSaveOrderThrowsWhenProductsIsEmptyJsonArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['products' => '[]']));
    }

    public function testSaveOrderThrowsWhenProductsJsonIsInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        $this->controller()->saveOrder($this->pdo, $this->validPost(['products' => 'not-json']));
    }

    public function testSaveOrderThrowsWhenProductsFieldIsAbsent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        $post = $this->validPost();
        unset($post['products']);
        $this->controller()->saveOrder($this->pdo, $post);
    }

    // =========================================================================
    // Redirect URL construction (total_price appended as query param)
    // =========================================================================

    public function testRedirectUrlAppendsTotalPriceWithQuestionMark(): void
    {
        $controller  = $this->controller('/thank-you');
        $base        = $controller->getRedirectUrl();
        $totalPrice  = 19.99;
        $sep         = str_contains($base, '?') ? '&' : '?';

        self::assertSame(
            '/thank-you?total_price=19.99',
            $base . $sep . 'total_price=' . urlencode((string) $totalPrice)
        );
    }

    public function testRedirectUrlUsesAmpersandWhenQueryStringAlreadyPresent(): void
    {
        $controller = $this->controller('/thank-you?ref=email');
        $base       = $controller->getRedirectUrl();
        $totalPrice = 5.00;
        $sep        = str_contains($base, '?') ? '&' : '?';

        self::assertSame(
            '/thank-you?ref=email&total_price=5',
            $base . $sep . 'total_price=' . urlencode((string) $totalPrice)
        );
    }

    public function testRedirectUrlTotalPriceIsUrlEncoded(): void
    {
        $controller = $this->controller('/thank-you');
        $base       = $controller->getRedirectUrl();
        $totalPrice = 1234.56;
        $sep        = str_contains($base, '?') ? '&' : '?';
        $final      = $base . $sep . 'total_price=' . urlencode((string) $totalPrice);

        self::assertStringContainsString('total_price=1234.56', $final);
        self::assertStringStartsWith('/thank-you', $final);
    }
}
