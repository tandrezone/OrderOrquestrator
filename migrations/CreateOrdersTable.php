<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Migrations;

use PDO;

/**
 * Migration: creates the `orders` table.
 *
 * Safe to call multiple times (CREATE TABLE IF NOT EXISTS).
 *
 * Usage:
 *   $migration = new CreateOrdersTable();
 *   $migration->run($pdo);
 */
class CreateOrdersTable
{
    /**
     * Execute the migration against the given database connection.
     */
    public function run(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
                customer_name    VARCHAR(255)    NOT NULL,
                customer_email   VARCHAR(255)    NOT NULL,
                shipping_address VARCHAR(500)    NOT NULL,
                total_price      DECIMAL(12, 2)  NOT NULL,
                products         JSON            NOT NULL,
                created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_orders_customer_email (customer_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
