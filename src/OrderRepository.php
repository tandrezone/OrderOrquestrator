<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

use PDO;
use RuntimeException;

/**
 * Low-level persistence layer for the `orders` table.
 *
 * Call OrderRepository::createTable() once (e.g. from a migration script)
 * to set up the schema. All other methods assume the table already exists.
 */
final class OrderRepository
{
    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    /**
     * Creates the `orders` table if it does not already exist.
     * Safe to call multiple times (idempotent).
     */
    public function createTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_number         VARCHAR(50)  NOT NULL UNIQUE,

                -- Customer information (mirrors checkout.html fields)
                first_name           VARCHAR(100) NOT NULL,
                last_name            VARCHAR(100) NOT NULL,
                email                VARCHAR(255) NOT NULL,
                address              VARCHAR(500) NOT NULL,
                zip                  VARCHAR(20)  NOT NULL,
                city                 VARCHAR(100) NOT NULL,
                phone                VARCHAR(30)  DEFAULT NULL,

                -- Order financials
                shipping_method      VARCHAR(50)  NOT NULL,
                shipping_price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_gateway      VARCHAR(50)  NOT NULL,
                subtotal             DECIMAL(12,2) NOT NULL,
                total                DECIMAL(12,2) NOT NULL,

                -- Cart snapshot (JSON array of items)
                items                JSON         NOT NULL,

                -- Order lifecycle
                status               ENUM(
                    'pending','paid','processing',
                    'shipped','delivered','cancelled','refunded'
                ) NOT NULL DEFAULT 'pending',

                -- Tracking
                tracking_carrier     VARCHAR(100) DEFAULT NULL,
                tracking_number      VARCHAR(255) DEFAULT NULL,
                tracking_url         VARCHAR(500) DEFAULT NULL,
                tracking_status      VARCHAR(50)  DEFAULT NULL,
                tracking_last_checked TIMESTAMP   NULL DEFAULT NULL,

                -- Timestamps
                notes                TEXT         DEFAULT NULL,
                created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                shipped_at           TIMESTAMP    NULL DEFAULT NULL,
                delivered_at         TIMESTAMP    NULL DEFAULT NULL,

                INDEX idx_orders_email  (email),
                INDEX idx_orders_status (status),
                INDEX idx_orders_tracking_carrier (tracking_carrier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Persist a new order and return its auto-increment ID.
     *
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   address: string,
     *   zip: string,
     *   city: string,
     *   phone?: string|null,
     *   shipping_method: string,
     *   shipping_price: float|int,
     *   payment_gateway: string,
     *   subtotal: float|int,
     *   total: float|int,
     *   items: array<int, mixed>,
     *   order_number?: string,
     *   notes?: string|null,
     * } $data
     */
    public function save(PDO $pdo, array $data): int
    {
        $orderNumber = $data['order_number'] ?? $this->generateOrderNumber();

        $itemsJson = json_encode($data['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, first_name, last_name, email,
                address, zip, city, phone,
                shipping_method, shipping_price, payment_gateway,
                subtotal, total, items, notes
            ) VALUES (
                :order_number, :first_name, :last_name, :email,
                :address, :zip, :city, :phone,
                :shipping_method, :shipping_price, :payment_gateway,
                :subtotal, :total, :items, :notes
            )
        ");

        $stmt->execute([
            ':order_number'    => $orderNumber,
            ':first_name'      => $data['first_name'],
            ':last_name'       => $data['last_name'],
            ':email'           => $data['email'],
            ':address'         => $data['address'],
            ':zip'             => $data['zip'],
            ':city'            => $data['city'],
            ':phone'           => $data['phone'] ?? null,
            ':shipping_method' => $data['shipping_method'],
            ':shipping_price'  => (float) ($data['shipping_price'] ?? 0),
            ':payment_gateway' => $data['payment_gateway'],
            ':subtotal'        => (float) $data['subtotal'],
            ':total'           => (float) $data['total'],
            ':items'           => $itemsJson,
            ':notes'           => $data['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update the status of an existing order.
     * Automatically sets `shipped_at` / `delivered_at` timestamps.
     */
    public function updateStatus(PDO $pdo, int $orderId, OrderStatus $status): void
    {
        $extra = '';
        if ($status === OrderStatus::Shipped) {
            $extra = ', shipped_at = COALESCE(shipped_at, NOW())';
        } elseif ($status === OrderStatus::Delivered) {
            $extra = ', delivered_at = COALESCE(delivered_at, NOW())';
        }

        $stmt = $pdo->prepare(
            "UPDATE orders SET status = :status{$extra} WHERE id = :id"
        );
        $stmt->execute([':status' => $status->value, ':id' => $orderId]);
    }

    /**
     * Attach tracking information to an order and advance its status to 'shipped'.
     */
    public function updateTracking(
        PDO $pdo,
        int $orderId,
        string $carrier,
        string $trackingNumber,
        ?string $trackingUrl = null,
    ): void {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET
                tracking_carrier = :carrier,
                tracking_number  = :tracking_number,
                tracking_url     = :tracking_url,
                status           = CASE WHEN status IN ('pending','paid','processing')
                                        THEN 'shipped'
                                        ELSE status
                                   END,
                shipped_at       = COALESCE(shipped_at, NOW())
            WHERE id = :id
        ");

        $stmt->execute([
            ':carrier'         => strtolower($carrier),
            ':tracking_number' => $trackingNumber,
            ':tracking_url'    => $trackingUrl,
            ':id'              => $orderId,
        ]);
    }

    /**
     * Persist the latest tracking status string and refresh the last-checked timestamp.
     * Called automatically by TrackingManager after each provider query.
     */
    public function updateTrackingStatus(PDO $pdo, int $orderId, string $trackingStatus): void
    {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET tracking_status = :tracking_status,
                tracking_last_checked = NOW()
            WHERE id = :id
        ");

        $stmt->execute([':tracking_status' => $trackingStatus, ':id' => $orderId]);
    }

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Find a single order by its primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $this->decodeItems($row) : null;
    }

    /**
     * Find a single order by its human-readable order number.
     *
     * @return array<string, mixed>|null
     */
    public function findByOrderNumber(PDO $pdo, string $orderNumber): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :order_number LIMIT 1');
        $stmt->execute([':order_number' => $orderNumber]);
        $row = $stmt->fetch();

        return $row !== false ? $this->decodeItems($row) : null;
    }

    /**
     * Returns all orders with status 'shipped' that have a tracking number set
     * and have not yet been marked as delivered or reached a final state.
     * Used by TrackingManager::checkAllShipped().
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPendingShipments(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT * FROM orders
            WHERE status = 'shipped'
              AND tracking_number IS NOT NULL
              AND tracking_carrier IS NOT NULL
            ORDER BY shipped_at ASC
        ");

        return array_map(
            fn(array $row) => $this->decodeItems($row),
            $stmt->fetchAll()
        );
    }

    /**
     * Paginated list of all orders, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(PDO $pdo, int $limit = 50, int $offset = 0): array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => $this->decodeItems($row),
            $stmt->fetchAll()
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Decode the JSON items column back to a PHP array. */
    private function decodeItems(array $row): array
    {
        if (isset($row['items']) && is_string($row['items'])) {
            $row['items'] = json_decode($row['items'], true) ?? [];
        }

        return $row;
    }

    /** Generate a unique, human-readable order number: ORD-YYYYMMDD-XXXXXXXX */
    private function generateOrderNumber(): string
    {
        return sprintf('ORD-%s-%s', date('Ymd'), strtoupper(bin2hex(random_bytes(4))));
    }
}
