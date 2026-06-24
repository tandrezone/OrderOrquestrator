<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

final class OrderOrquestrator
{
    /** @var array<string, array<string, bool|string|null>> */
    private const ORDERS_TABLE_COLUMNS = [
        'id' => [
            'type' => 'INT UNSIGNED',
            'nullable' => false,
            'auto_increment' => true,
            'primary_key' => true,
            'default' => null,
        ],
        'customer_name' => ['type' => 'VARCHAR(255)', 'nullable' => false, 'default' => null],
        'customer_email' => ['type' => 'VARCHAR(255)', 'nullable' => false, 'default' => null],
        'shipping_address' => ['type' => 'VARCHAR(500)', 'nullable' => false, 'default' => null],
        'total_price' => ['type' => 'DECIMAL(12,2)', 'nullable' => false, 'default' => null],
        'products' => ['type' => 'JSON', 'nullable' => false, 'default' => null],
        'created_at' => ['type' => 'TIMESTAMP', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
        'updated_at' => ['type' => 'TIMESTAMP', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ];

    public function __construct(
        private readonly string $projectRoot = __DIR__,
    ) {
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function templatesDirectory(): string
    {
        return $this->projectRoot . '/resources/templates';
    }

    public function routesFile(): string
    {
        return $this->projectRoot . '/routes/routes.php';
    }

    public function migrationsDirectory(): string
    {
        return $this->projectRoot . '/migrations';
    }

    public function configFile(): string
    {
        return $this->projectRoot . '/config/config.php';
    }

    public function scriptsDirectory(): string
    {
        return $this->projectRoot . '/bin';
    }

    public function resourcesDirectory(): string
    {
        return $this->projectRoot . '/resources';
    }

    public function shippingMethodsFile(): string
    {
        return $this->resourcesDirectory() . '/shipping-methods.json';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function routeDefinitions(): array
    {
        $file = $this->routesFile();

        if (!is_file($file)) {
            throw new \RuntimeException("Routes file not found: {$file}");
        }

        $routes = require $file;

        if (!is_array($routes)) {
            throw new \RuntimeException("Routes file did not return an array: {$file}");
        }

        /** @var array<int, array<string, mixed>> $routes */
        return $routes;
    }

    /**
     * @return array<string, mixed>
     */
    public function entryPointRoute(): array
    {
        foreach ($this->routeDefinitions() as $route) {
            if (($route['method'] ?? '') === 'GET' && ($route['path'] ?? '') === '/order') {
                return $route;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function requiredEntryPointData(): array
    {
        return $this->entryPointRoute()['parameters'] ?? [];
    }

    /**
     * @return array{
     *   table:string,
     *   source:string,
     *   columns:array<string, array<string, bool|string|null>>
     * }
     */
    public function ordersTableSchema(): array
    {
        return [
            'table' => 'orders',
            'source' => $this->projectRoot . '/migrations/CreateOrdersTable.php',
            'columns' => self::ORDERS_TABLE_COLUMNS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $entryPoint = $this->entryPointRoute();

        return [
            'paths' => [
                'root' => $this->projectRoot(),
                'templates' => [
                    'directory' => $this->templatesDirectory(),
                    'files' => [
                        $this->templatesDirectory() . '/order-form.html',
                        $this->templatesDirectory() . '/order.html',
                        $this->templatesDirectory() . '/confirmation.html',
                        $this->templatesDirectory() . '/admin/orders.html',
                    ],
                ],
                'routes' => [
                    'file' => $this->routesFile(),
                ],
                'migrations' => [
                    'directory' => $this->migrationsDirectory(),
                    'files' => [
                        $this->migrationsDirectory() . '/CreateOrdersTable.php',
                    ],
                ],
                'configs' => [
                    'file' => $this->configFile(),
                ],
                'scripts' => [
                    'directory' => $this->scriptsDirectory(),
                    'files' => [
                        $this->scriptsDirectory() . '/install-order-template',
                        $this->scriptsDirectory() . '/create-orders-table',
                    ],
                ],
                'resources' => [
                    'directory' => $this->resourcesDirectory(),
                    'shipping_methods' => $this->shippingMethodsFile(),
                ],
            ],
            'entry_point' => [
                'method' => $entryPoint['method'] ?? null,
                'path' => $entryPoint['path'] ?? null,
                'parameters' => $entryPoint['parameters'] ?? [],
            ],
            'required_data' => $this->requiredEntryPointData(),
            'orders_table' => $this->ordersTableSchema(),
        ];
    }
}
