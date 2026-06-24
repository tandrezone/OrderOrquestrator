<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\OrderOrquestrator;

final class OrderOrquestratorManifestTest extends TestCase
{
    private OrderOrquestrator $package;

    protected function setUp(): void
    {
        $this->package = new OrderOrquestrator(dirname(__DIR__));
    }

    public function testDescribeIncludesMainProjectLocations(): void
    {
        $description = $this->package->describe();

        self::assertSame(dirname(__DIR__), $description['paths']['root']);
        self::assertSame(dirname(__DIR__) . '/resources/templates', $description['paths']['templates']['directory']);
        self::assertSame(dirname(__DIR__) . '/routes/routes.php', $description['paths']['routes']['file']);
        self::assertSame(dirname(__DIR__) . '/migrations', $description['paths']['migrations']['directory']);
        self::assertSame(dirname(__DIR__) . '/config/config.php', $description['paths']['configs']['file']);
        self::assertSame(dirname(__DIR__) . '/bin', $description['paths']['scripts']['directory']);
    }

    public function testEntryPointRouteMatchesRoutesDefinition(): void
    {
        $route = $this->package->entryPointRoute();

        self::assertSame('GET', $route['method']);
        self::assertSame('/order', $route['path']);
        self::assertArrayHasKey('products', $route['parameters']);
    }

    public function testRequiredEntryPointDataDescribesProductsPayload(): void
    {
        $requiredData = $this->package->requiredEntryPointData();

        self::assertSame('array', $requiredData['products']['type']);
        self::assertSame('integer', $requiredData['products']['structure']['id']);
        self::assertSame('string', $requiredData['products']['structure']['name']);
        self::assertSame('float', $requiredData['products']['structure']['price']);
        self::assertSame('integer', $requiredData['products']['structure']['quantity']);
    }

    public function testOrdersTableSchemaExposesCurrentColumns(): void
    {
        $schema = $this->package->ordersTableSchema();

        self::assertSame('orders', $schema['table']);
        self::assertSame(dirname(__DIR__) . '/migrations/CreateOrdersTable.php', $schema['source']);
        self::assertSame('VARCHAR(255)', $schema['columns']['customer_name']['type']);
        self::assertSame('VARCHAR(255)', $schema['columns']['customer_email']['type']);
        self::assertSame('DECIMAL(12,2)', $schema['columns']['total_price']['type']);
        self::assertSame('CURRENT_TIMESTAMP', $schema['columns']['created_at']['default']);
        self::assertTrue($schema['columns']['id']['primary_key']);
    }
}
