<?php

namespace App\Modules\NotificationCenter\Observers;

use App\Modules\NotificationCenter\Services\Detectors\InventoryDetector;
use App\Modules\Sales\Models\Sale;

/**
 * Fires immediately after a sale row is created so the inventory
 * detector can re-evaluate stock levels in real time. Without this
 * the cashier would only see low-stock alerts after the next
 * scheduled detector run (10-min interval). With it, the bell icon
 * lights up the moment a sale drops a SKU below its reorder level.
 *
 * Runs synchronously because the detector itself is cheap (single
 * indexed query per domain). For very high-volume installs the call
 * can be wrapped in a queued job without changing semantics.
 */
class SaleNotificationObserver
{
    public function __construct(private InventoryDetector $inventory) {}

    public function created(Sale $sale): void
    {
        try {
            $this->inventory->run();
        } catch (\Throwable $e) {
            // Never block a sale because notifications failed.
            report($e);
        }
    }
}
