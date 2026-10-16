<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Stored in the "notifications" table (database channel) and read through
 * GET /api/v1/notifications. It is sent from an already queued job, so the
 * notification itself is not queued again.
 */
final class LowStockNotification extends Notification
{
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $productName,
        public readonly int $warehouseId,
        public readonly string $warehouseCode,
        public readonly int $available,
        public readonly int $threshold,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Stable type stored instead of the PHP class name.
     */
    public function databaseType(object $notifiable): string
    {
        return 'low_stock';
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'product_name' => $this->productName,
            'warehouse_id' => $this->warehouseId,
            'warehouse_code' => $this->warehouseCode,
            'available' => $this->available,
            'threshold' => $this->threshold,
            'message' => "{$this->sku} is low in {$this->warehouseCode}: {$this->available} available (threshold {$this->threshold}).",
        ];
    }
}
