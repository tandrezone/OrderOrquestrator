<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\Controllers\ConfirmationController;
use Tandrezone\OrderOrchestrator\Controllers\OrderController;

/**
 * Verifies the shape, methods, paths, parameter specs, and callbacks of routes/routes.php.
 *
 * The file must expose exactly four definitions:
 *   [0] GET  /order              — package entry point (requires products input)
 *   [1] POST /order              — form submission → confirmation view
 *   [2] POST /confirmation       — save order → redirect
 *   [3] GET  config[…]           — package return/redirect point (returns total_price)
 */
final class RoutesTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $routes;

    protected function setUp(): void
    {
        $this->routes = require dirname(__DIR__) . '/routes/routes.php';
    }

    // -------------------------------------------------------------------------
    // Top-level structure
    // -------------------------------------------------------------------------

    public function testRoutesFileReturnsAnArray(): void
    {
        self::assertIsArray($this->routes);
    }

    public function testRoutesFileContainsFourDefinitions(): void
    {
        self::assertCount(4, $this->routes);
    }

    public function testEveryRouteHasRequiredKeys(): void
    {
        foreach ($this->routes as $index => $route) {
            self::assertArrayHasKey('method',     $route, "Route #{$index} missing 'method'");
            self::assertArrayHasKey('path',       $route, "Route #{$index} missing 'path'");
            self::assertArrayHasKey('parameters', $route, "Route #{$index} missing 'parameters'");
        }
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    public function testGetOrderRouteCallback(): void
    {
        self::assertSame([OrderController::class, 'showForm'], $this->routes[0]['callback']);
    }

    public function testPostOrderRouteCallback(): void
    {
        self::assertSame([OrderController::class, 'processForm'], $this->routes[1]['callback']);
    }

    public function testPostConfirmationRouteCallback(): void
    {
        self::assertSame([ConfirmationController::class, 'processConfirmation'], $this->routes[2]['callback']);
    }

    public function testEveryRouteParametersValueIsAnArray(): void
    {
        foreach ($this->routes as $index => $route) {
            self::assertIsArray($route['parameters'], "Route #{$index} 'parameters' is not an array");
        }
    }

    public function testEveryRouteMethodIsUpperCase(): void
    {
        foreach ($this->routes as $index => $route) {
            self::assertSame(
                strtoupper($route['method']),
                $route['method'],
                "Route #{$index} method '{$route['method']}' is not upper-case"
            );
        }
    }

    // -------------------------------------------------------------------------
    // [0] GET /order — package entry point
    // -------------------------------------------------------------------------

    public function testEntryRouteIsGetOrder(): void
    {
        self::assertSame('GET',    $this->routes[0]['method']);
        self::assertSame('/order', $this->routes[0]['path']);
    }

    public function testEntryRouteHasProductsParameter(): void
    {
        self::assertArrayHasKey('products', $this->routes[0]['parameters']);
    }

    public function testEntryRouteProductsIsTypedArray(): void
    {
        $products = $this->routes[0]['parameters']['products'];
        self::assertArrayHasKey('type', $products);
        self::assertSame('array', $products['type']);
    }

    public function testEntryRouteProductsHasStructureKey(): void
    {
        self::assertArrayHasKey('structure', $this->routes[0]['parameters']['products']);
    }

    public function testEntryRouteProductsStructureContainsAllFields(): void
    {
        $structure = $this->routes[0]['parameters']['products']['structure'];

        self::assertArrayHasKey('id',       $structure);
        self::assertArrayHasKey('image',    $structure);
        self::assertArrayHasKey('name',     $structure);
        self::assertArrayHasKey('price',    $structure);
        self::assertArrayHasKey('quantity', $structure);
    }

    public function testEntryRouteProductsStructureFieldTypes(): void
    {
        $s = $this->routes[0]['parameters']['products']['structure'];

        self::assertSame('integer', $s['id']);
        self::assertSame('string',  $s['image']);
        self::assertSame('string',  $s['name']);
        self::assertSame('float',   $s['price']);
        self::assertSame('integer', $s['quantity']);
    }

    // -------------------------------------------------------------------------
    // [1] POST /order — form submission
    // -------------------------------------------------------------------------

    public function testPostOrderRouteMethodAndPath(): void
    {
        self::assertSame('POST',   $this->routes[1]['method']);
        self::assertSame('/order', $this->routes[1]['path']);
    }

    public function testPostOrderRouteHasStringCustomerFields(): void
    {
        $params = $this->routes[1]['parameters'];

        self::assertSame('string', $params['customer_name']);
        self::assertSame('string', $params['customer_email']);
        self::assertSame('string', $params['shipping_address']);
    }

    public function testPostOrderRouteProductsTypeIsArray(): void
    {
        $products = $this->routes[1]['parameters']['products'];
        self::assertSame('array', $products['type']);
    }

    public function testPostOrderRouteProductsStructureContainsIdImageNamePriceQuantity(): void
    {
        $s = $this->routes[1]['parameters']['products']['structure'];

        self::assertSame('integer', $s['id']);
        self::assertSame('string',  $s['image']);
        self::assertSame('string',  $s['name']);
        self::assertSame('float',   $s['price']);
        self::assertSame('integer', $s['quantity']);
    }

    // -------------------------------------------------------------------------
    // [2] POST /confirmation — save order
    // -------------------------------------------------------------------------

    public function testConfirmationRouteMethodAndPath(): void
    {
        self::assertSame('POST',          $this->routes[2]['method']);
        self::assertSame('/confirmation', $this->routes[2]['path']);
    }

    public function testConfirmationRouteHasAllCustomerStringFields(): void
    {
        $params = $this->routes[2]['parameters'];

        self::assertSame('string', $params['customer_name']);
        self::assertSame('string', $params['customer_email']);
        self::assertSame('string', $params['shipping_address']);
    }

    public function testConfirmationRouteHasTotalPriceAsFloat(): void
    {
        self::assertSame('float', $this->routes[2]['parameters']['total_price']);
    }

    public function testConfirmationRouteProductsTypeIsArray(): void
    {
        self::assertSame('array', $this->routes[2]['parameters']['products']['type']);
    }

    public function testConfirmationRouteProductsStructureHasIdNamePriceQuantity(): void
    {
        $s = $this->routes[2]['parameters']['products']['structure'];

        self::assertSame('integer', $s['id']);
        self::assertSame('string',  $s['name']);
        self::assertSame('float',   $s['price']);
        self::assertSame('integer', $s['quantity']);
    }

    /** The confirmation route does not need image — it is a display-only field. */
    public function testConfirmationRouteProductsStructureDoesNotIncludeImage(): void
    {
        $s = $this->routes[2]['parameters']['products']['structure'];
        self::assertArrayNotHasKey('image', $s);
    }

    // -------------------------------------------------------------------------
    // [3] Return route — redirect after order saved
    // -------------------------------------------------------------------------

    public function testReturnRouteMethodIsGet(): void
    {
        self::assertSame('GET', $this->routes[3]['method']);
    }

    public function testReturnRouteHasTotalPriceFloatParameter(): void
    {
        $params = $this->routes[3]['parameters'];

        self::assertArrayHasKey('total_price', $params);
        self::assertSame('float', $params['total_price']);
    }

    public function testReturnRoutePathReferencesConfigKey(): void
    {
        // The path must mention 'config' so consumers know it is application-defined.
        self::assertStringContainsStringIgnoringCase('config', (string) $this->routes[3]['path']);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    /** The two /order routes must be distinguishable by method. */
    public function testOrderPathAppearsWithBothGetAndPost(): void
    {
        $orderRoutes = array_filter($this->routes, static fn (array $r) => $r['path'] === '/order');

        $methods = array_column(array_values($orderRoutes), 'method');
        sort($methods);

        self::assertSame(['GET', 'POST'], $methods);
    }

    /** Paths that start with / should not have trailing slashes. */
    public function testPathsDontHaveTrailingSlash(): void
    {
        foreach ($this->routes as $route) {
            $path = (string) $route['path'];
            if (str_starts_with($path, '/')) {
                self::assertStringEndsNotWith('/', $path, "Route path '{$path}' has a trailing slash");
            }
        }
    }

    /** price fields must be declared as float, not integer or string. */
    public function testPriceFieldsAreDeclaredAsFloat(): void
    {
        foreach ($this->routes as $route) {
            $products = $route['parameters']['products'] ?? null;
            if (!is_array($products) || !isset($products['structure']['price'])) {
                continue;
            }
            self::assertSame('float', $products['structure']['price'], "price must be float in {$route['method']} {$route['path']}");
        }
    }

    /** quantity and id fields must be declared as integer. */
    public function testIntegerFieldsAreDeclaredAsInteger(): void
    {
        foreach ($this->routes as $route) {
            $structure = $route['parameters']['products']['structure'] ?? null;
            if (!is_array($structure)) {
                continue;
            }
            foreach (['id', 'quantity'] as $field) {
                if (array_key_exists($field, $structure)) {
                    self::assertSame('integer', $structure[$field], "'{$field}' must be integer in {$route['method']} {$route['path']}");
                }
            }
        }
    }
}
