<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tandrezone\OrderOrchestrator\Controllers\OrderController;
use Tandrezone\Ztemp\TemplateEngine;

/**
 * Unit tests for OrderController (GET /order and POST /order).
 *
 * TemplateEngine is always mocked so no template files are required on disk.
 */
final class OrderControllerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an OrderController whose TemplateEngine captures every render() call.
     *
     * @param array<string, array<string, mixed>> $captured  Populated by reference.
     */
    private function controllerCapturing(array &$captured): OrderController
    {
        $engine = $this->createMock(TemplateEngine::class);
        $engine->method('render')
            ->willReturnCallback(static function (string $tpl, array $params) use (&$captured): string {
                $captured[$tpl] = $params;
                return "<html>{$tpl}</html>";
            });

        return new OrderController($engine);
    }

    /** Minimal valid product for use in tests. */
    private function product(int $id = 1, float $price = 10.00, int $qty = 1): array
    {
        return ['id' => $id, 'image' => "/img/{$id}.png", 'name' => "Product {$id}", 'price' => $price, 'quantity' => $qty];
    }

    /** Minimal valid POST data for processForm(). */
    private function validPost(array $overrides = []): array
    {
        return array_merge([
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'shipping_address' => '123 Main St',
            'products'         => json_encode([$this->product()]),
        ], $overrides);
    }

    // =========================================================================
    // showForm — template name
    // =========================================================================

    public function testShowFormRendersOrderHtmlTemplate(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $engine->expects(self::once())
            ->method('render')
            ->with('order.html', self::anything())
            ->willReturn('<html>order</html>');

        (new OrderController($engine))->showForm([$this->product()]);
    }

    // =========================================================================
    // showForm — product enrichment
    // =========================================================================

    public function testShowFormAddsFormattedPriceToProduct(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            $this->product(1, 9.99, 1),
        ]);

        self::assertSame('9.99', $captured['order.html']['products'][0]['price_formatted']);
    }

    public function testShowFormAddsLineTotalToProduct(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            $this->product(1, 9.99, 3),
        ]);

        self::assertSame('29.97', $captured['order.html']['products'][0]['line_total_formatted']);
    }

    public function testShowFormCalculatesTotalAcrossMultipleProducts(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            $this->product(1, 10.00, 1), // 10.00
            $this->product(2,  5.50, 2), // 11.00
        ]);

        self::assertSame('21.00', $captured['order.html']['total_price']);
    }

    public function testShowFormDefaultsQuantityToOneWhenMissing(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            ['id' => 1, 'image' => '/img/1.png', 'name' => 'No-qty item', 'price' => 8.00],
        ]);

        $product = $captured['order.html']['products'][0];
        self::assertSame(1, $product['quantity']);
        self::assertSame('8.00', $captured['order.html']['total_price']);
    }

    public function testShowFormDefaultsPriceToZeroWhenMissing(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            ['id' => 1, 'image' => '/img/1.png', 'name' => 'Free item'],
        ]);

        $product = $captured['order.html']['products'][0];
        self::assertSame('0.00', $product['price_formatted']);
        self::assertSame('0.00', $captured['order.html']['total_price']);
    }

    /** Quantity of 0 must be normalised to 1 (no zero-priced lines). */
    public function testShowFormNormalisesZeroQuantityToOne(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([
            ['id' => 1, 'image' => '', 'name' => 'X', 'price' => 5.00, 'quantity' => 0],
        ]);

        self::assertSame(1, $captured['order.html']['products'][0]['quantity']);
        self::assertSame('5.00', $captured['order.html']['total_price']);
    }

    // =========================================================================
    // showForm — products_json field
    // =========================================================================

    public function testShowFormIncludesProductsJsonField(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([$this->product(5)]);

        self::assertArrayHasKey('products_json', $captured['order.html']);
    }

    public function testShowFormProductsJsonIsDecodableJson(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([$this->product(5, 3.00)]);

        $json    = html_entity_decode($captured['order.html']['products_json']);
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame(5, $decoded[0]['id']);
    }

    public function testShowFormProductsJsonStripsDisplayOnlyKeys(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->showForm([$this->product(1, 7.50, 2)]);

        $json    = html_entity_decode($captured['order.html']['products_json']);
        $decoded = json_decode($json, true);

        self::assertArrayNotHasKey('price_formatted',      $decoded[0]);
        self::assertArrayNotHasKey('line_total_formatted', $decoded[0]);
    }

    // =========================================================================
    // processForm — validation errors
    // =========================================================================

    public function testProcessFormThrowsWhenCustomerNameIsEmpty(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer name is required.');

        (new OrderController($engine))->processForm($this->validPost(['customer_name' => '']));
    }

    public function testProcessFormThrowsWhenCustomerNameIsOnlyWhitespace(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer name is required.');

        (new OrderController($engine))->processForm($this->validPost(['customer_name' => '   ']));
    }

    public function testProcessFormThrowsForInvalidEmailMissingAt(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A valid email address is required.');

        (new OrderController($engine))->processForm($this->validPost(['customer_email' => 'not-an-email']));
    }

    public function testProcessFormThrowsForEmptyEmail(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A valid email address is required.');

        (new OrderController($engine))->processForm($this->validPost(['customer_email' => '']));
    }

    public function testProcessFormThrowsWhenShippingAddressIsEmpty(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shipping address is required.');

        (new OrderController($engine))->processForm($this->validPost(['shipping_address' => '']));
    }

    public function testProcessFormThrowsWhenShippingAddressIsOnlyWhitespace(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shipping address is required.');

        (new OrderController($engine))->processForm($this->validPost(['shipping_address' => '   ']));
    }

    public function testProcessFormThrowsWhenProductsIsEmptyJsonArray(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        (new OrderController($engine))->processForm($this->validPost(['products' => '[]']));
    }

    public function testProcessFormThrowsWhenProductsJsonIsInvalid(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        (new OrderController($engine))->processForm($this->validPost(['products' => '{not-json}']));
    }

    public function testProcessFormThrowsWhenProductsFieldIsAbsent(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one product is required.');

        $post = $this->validPost();
        unset($post['products']);
        (new OrderController($engine))->processForm($post);
    }

    // =========================================================================
    // processForm — template name
    // =========================================================================

    public function testProcessFormRendersConfirmationHtmlTemplate(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $engine->expects(self::once())
            ->method('render')
            ->with('confirmation.html', self::anything())
            ->willReturn('<html>confirm</html>');

        (new OrderController($engine))->processForm($this->validPost());
    }

    // =========================================================================
    // processForm — payload content
    // =========================================================================

    public function testProcessFormPassesCustomerDataToConfirmationTemplate(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost());

        $payload = $captured['confirmation.html'];
        self::assertStringContainsString('Jane Doe',        $payload['customer_name']);
        self::assertStringContainsString('jane@example.com', $payload['customer_email']);
        self::assertStringContainsString('123 Main St',     $payload['shipping_address']);
    }

    public function testProcessFormCalculatesTotalPriceCorrectly(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost([
            'products' => json_encode([
                $this->product(1, 5.00, 2),  // 10.00
                $this->product(2, 3.33, 1),  //  3.33
            ]),
        ]));

        $payload = $captured['confirmation.html'];
        self::assertSame('13.33', $payload['total_price']);
        self::assertSame('13.33', $payload['total_price_raw']);
    }

    public function testProcessFormIncludesEnrichedProductsInPayload(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost([
            'products' => json_encode([$this->product(1, 4.50, 2)]),
        ]));

        $product = $captured['confirmation.html']['products'][0];
        self::assertSame('4.50',  $product['price_formatted']);
        self::assertSame('9.00',  $product['line_total_formatted']);
    }

    // =========================================================================
    // processForm — security / edge cases
    // =========================================================================

    public function testProcessFormEscapesHtmlInCustomerName(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost([
            'customer_name' => '<script>alert("xss")</script>',
        ]));

        $name = $captured['confirmation.html']['customer_name'];
        self::assertStringNotContainsString('<script>', $name);
        self::assertStringContainsString('&lt;script&gt;', $name);
    }

    public function testProcessFormEscapesHtmlInShippingAddress(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost([
            'shipping_address' => '1 Main & "Side" St <apt 2>',
        ]));

        $addr = $captured['confirmation.html']['shipping_address'];
        self::assertStringNotContainsString('<apt', $addr);
        self::assertStringContainsString('&lt;apt', $addr);
    }

    /** Email addresses with + signs are valid and must not throw. */
    public function testProcessFormAcceptsEmailWithPlusSign(): void
    {
        $engine = $this->createMock(TemplateEngine::class);
        $engine->method('render')->willReturn('');

        (new OrderController($engine))->processForm($this->validPost([
            'customer_email' => 'jane+orders@example.com',
        ]));

        $this->addToAssertionCount(1); // no exception = pass
    }

    /** Float precision: 3 items at $0.10 each should total $0.30, not $0.30000000004. */
    public function testProcessFormRoundsTotalPriceToTwoDecimalPlaces(): void
    {
        $captured = [];
        $this->controllerCapturing($captured)->processForm($this->validPost([
            'products' => json_encode([
                $this->product(1, 0.10, 3),
            ]),
        ]));

        self::assertSame('0.30', $captured['confirmation.html']['total_price']);
    }
}
