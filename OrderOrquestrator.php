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
        'order_number' => [
            'type' => 'VARCHAR(50)',
            'nullable' => false,
            'unique' => true,
            'default' => null,
        ],
        'first_name' => ['type' => 'VARCHAR(100)', 'nullable' => false, 'default' => null],
        'last_name' => ['type' => 'VARCHAR(100)', 'nullable' => false, 'default' => null],
        'email' => ['type' => 'VARCHAR(255)', 'nullable' => false, 'default' => null],
        'address' => ['type' => 'VARCHAR(500)', 'nullable' => false, 'default' => null],
        'zip' => ['type' => 'VARCHAR(20)', 'nullable' => false, 'default' => null],
        'city' => ['type' => 'VARCHAR(100)', 'nullable' => false, 'default' => null],
        'phone' => ['type' => 'VARCHAR(30)', 'nullable' => true, 'default' => null],
        'shipping_method' => ['type' => 'VARCHAR(50)', 'nullable' => false, 'default' => null],
        'shipping_price' => ['type' => 'DECIMAL(12,2)', 'nullable' => false, 'default' => '0.00'],
        'payment_gateway' => ['type' => 'VARCHAR(50)', 'nullable' => false, 'default' => null],
        'subtotal' => ['type' => 'DECIMAL(12,2)', 'nullable' => false, 'default' => null],
        'total' => ['type' => 'DECIMAL(12,2)', 'nullable' => false, 'default' => null],
        'items' => ['type' => 'JSON', 'nullable' => false, 'default' => null],
        'status' => [
            'type' => "ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded')",
            'nullable' => false,
            'default' => 'pending',
        ],
        'tracking_carrier' => ['type' => 'VARCHAR(100)', 'nullable' => true, 'default' => null],
        'tracking_number' => ['type' => 'VARCHAR(255)', 'nullable' => true, 'default' => null],
        'tracking_url' => ['type' => 'VARCHAR(500)', 'nullable' => true, 'default' => null],
        'tracking_status' => ['type' => 'VARCHAR(50)', 'nullable' => true, 'default' => null],
        'tracking_last_checked' => ['type' => 'TIMESTAMP', 'nullable' => true, 'default' => null],
        'notes' => ['type' => 'TEXT', 'nullable' => true, 'default' => null],
        'created_at' => ['type' => 'TIMESTAMP', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
        'updated_at' => ['type' => 'TIMESTAMP', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        'shipped_at' => ['type' => 'TIMESTAMP', 'nullable' => true, 'default' => null],
        'delivered_at' => ['type' => 'TIMESTAMP', 'nullable' => true, 'default' => null],
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
        /** @var array<int, array<string, mixed>> $routes */
        $routes = require $this->routesFile();

        return $routes;
    }

    /**
     * @return array<string, mixed>
     */
    public function entryPointRoute(): array
    {
        return $this->routeDefinitions()[0] ?? [];
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
            'source' => $this->projectRoot . '/src/OrderRepository.php',
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
