<?php

namespace App\Modules\Serials\Services;

use App\Modules\Serials\Models\ProductSerial;
use App\Modules\Serials\Models\ProductSerialMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
 |--------------------------------------------------------------------------
 | SerialMovementService — the only place serial.status should mutate
 |--------------------------------------------------------------------------
 |
 | Every status transition on product_serials goes through one of the
 | record* methods here. Each method opens a transaction, flips the
 | status (plus any related foreign keys) and writes one audit row to
 | product_serial_movements in a single atomic block. The audit row is
 | append-only — it's never updated or deleted.
 |
 | Why not Eloquent observers? Observers can't carry the polymorphic
 | reference cleanly (we'd need a static stash) and an explicit service
 | makes it trivial to reason about which document caused a status
 | change. Returning to observers later for cross-cutting concerns is
 | possible without touching this contract.
 */
class SerialMovementService
{
    /**
     * Mark a serial as received from a supplier.
     * Triggered by PurchaseService::confirmReceive().
     */
    public function recordPurchase(ProductSerial $serial, string $refType, int $refId, ?int $toBranchId = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $refType, $refId, $toBranchId) {
            $serial->forceFill([
                'status'         => ProductSerial::STATUS_IN_STOCK,
                'purchase_id'    => $refId,
                'branch_id'      => $toBranchId ?? $serial->branch_id,
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_PURCHASE, $refType, $refId, [
                'to_branch_id' => $toBranchId ?? $serial->branch_id,
            ]);
        });
    }

    /**
     * Mark a serial as sold to a customer. Caller (SaleService) has
     * already validated that the serial is in_stock and belongs to the
     * cashier's branch.
     */
    public function recordSale(ProductSerial $serial, string $refType, int $refId, ?int $customerId = null, ?int $saleItemId = null): ProductSerialMovement
    {
        $movement = DB::transaction(function () use ($serial, $refType, $refId, $customerId, $saleItemId) {
            $serial->forceFill([
                'status'        => ProductSerial::STATUS_SOLD,
                'sale_id'       => $refId,
                'sale_item_id'  => $saleItemId,
                'customer_id'   => $customerId,
                'sale_date'     => now()->toDateString(),
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_SALE, $refType, $refId);
        });

        // Phase Y Round 2C — after each sale, check whether the in-stock
        // count for this product (on the serial's branch) has fallen
        // below the product's reorder threshold. If yes, fire the
        // "Serialized Product Low Stock" alert with a 6-hour cooldown
        // so cashiers selling out a batch don't spam the bell icon.
        try {
            $this->maybeFireLowStockAlert($serial);
        } catch (\Throwable $e) { /* never block the sale movement */ }

        return $movement;
    }

    /**
     * Fire the low-stock alert when sellable serials for this product +
     * branch drop below the configured reorder_level. Idempotent via
     * SmartNotificationService::push() dedupe_key + cooldown.
     */
    protected function maybeFireLowStockAlert(ProductSerial $serial): void
    {
        $product = $serial->product()->select(['id', 'name', 'sku', 'reorder_level', 'is_serialized'])->first();
        if (!$product || !$product->is_serialized) return;

        $threshold = (int) ($product->reorder_level ?? 0);
        if ($threshold <= 0) return;       // owner hasn't configured a threshold

        $available = ProductSerial::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $serial->branch_id)
            ->where('status', ProductSerial::STATUS_IN_STOCK)
            ->count();

        if ($available > $threshold) return;

        app(\App\Modules\NotificationCenter\Services\SmartNotificationService::class)->push([
            'category'         => 'inventory',
            'code'             => 'serials.low_stock',
            'severity'         => $available === 0 ? 'danger' : 'warning',
            'urgency'          => $available === 0 ? 90 : 65,
            'audience_role'    => 'admin',
            'branch_id'        => $serial->branch_id,
            'title'            => __('notifications.serials.lowStock.title', [
                'name'      => $product->name,
                'available' => $available,
                'threshold' => $threshold,
            ]),
            'message'          => __('notifications.serials.lowStock.message', [
                'name'      => $product->name,
                'sku'       => $product->sku,
                'available' => $available,
                'threshold' => $threshold,
            ]),
            'dedupe_key'       => 'serials.low_stock:'.$product->id.':'.($serial->branch_id ?? 0),
            'cooldown_minutes' => 360,                   // 6 hours
            'entity_type'      => \App\Modules\Product\Models\Product::class,
            'entity_id'        => $product->id,
            'meta'             => [
                'product_id' => $product->id,
                'branch_id'  => $serial->branch_id,
                'available'  => $available,
                'threshold'  => $threshold,
            ],
        ]);
    }

    /** Customer returned the device. Default disposition is back in stock. */
    public function recordSalesReturn(ProductSerial $serial, string $refType, int $refId, bool $resellable = true): ProductSerialMovement
    {
        $movement = DB::transaction(function () use ($serial, $refType, $refId, $resellable) {
            $serial->forceFill([
                'status'           => $resellable ? ProductSerial::STATUS_IN_STOCK : ProductSerial::STATUS_SALES_RETURNED,
                'sales_return_id'  => $refId,
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_SALES_RETURN, $refType, $refId);
        });

        // Phase Y Round 2C — non-resellable return = device came back
        // damaged. Fire the "Damaged Serial Returned" notification so the
        // branch manager investigates. Best-effort: never blocks the
        // status transition above if the notification module is down.
        if (!$resellable) {
            try {
                app(\App\Modules\NotificationCenter\Services\SmartNotificationService::class)->push([
                    'category'         => 'inventory',
                    'code'             => 'serials.damaged_return',
                    'severity'         => 'warning',
                    'urgency'          => 60,
                    'audience_role'    => 'admin',
                    'branch_id'        => $serial->branch_id,
                    'title'            => __('notifications.serials.damagedReturn.title'),
                    'message'          => __('notifications.serials.damagedReturn.message', ['sn' => $serial->serial_number]),
                    'dedupe_key'       => 'serials.damaged_return:'.$serial->serial_number,
                    'cooldown_minutes' => 1440,                    // once a day per serial
                    'entity_type'      => ProductSerial::class,
                    'entity_id'        => $serial->id,
                    'meta'             => ['product_id' => $serial->product_id, 'serial_number' => $serial->serial_number],
                ]);
            } catch (\Throwable $e) { /* never block the movement */ }
        }

        return $movement;
    }

    /** Send the device back to the supplier. Removes it from sellable stock. */
    public function recordPurchaseReturn(ProductSerial $serial, string $refType, int $refId): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $refType, $refId) {
            $serial->forceFill([
                'status'              => ProductSerial::STATUS_PURCHASE_RETURNED,
                'purchase_return_id'  => $refId,
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_PURCHASE_RETURN, $refType, $refId);
        });
    }

    public function recordTransfer(ProductSerial $serial, int $fromBranchId, int $toBranchId, ?string $refType = null, ?int $refId = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $fromBranchId, $toBranchId, $refType, $refId) {
            $serial->forceFill(['branch_id' => $toBranchId])->save();
            return $this->log($serial, ProductSerialMovement::MOVEMENT_TRANSFER, $refType, $refId, [
                'from_branch_id' => $fromBranchId,
                'to_branch_id'   => $toBranchId,
            ]);
        });
    }

    public function recordReserve(ProductSerial $serial, ?string $refType = null, ?int $refId = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $refType, $refId) {
            $serial->forceFill(['status' => ProductSerial::STATUS_RESERVED])->save();
            return $this->log($serial, ProductSerialMovement::MOVEMENT_RESERVE, $refType, $refId);
        });
    }

    public function recordUnreserve(ProductSerial $serial, ?string $refType = null, ?int $refId = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $refType, $refId) {
            $serial->forceFill(['status' => ProductSerial::STATUS_IN_STOCK])->save();
            return $this->log($serial, ProductSerialMovement::MOVEMENT_UNRESERVE, $refType, $refId);
        });
    }

    public function recordDamage(ProductSerial $serial, ?string $remarks = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $remarks) {
            $serial->forceFill(['status' => ProductSerial::STATUS_DAMAGED])->save();
            return $this->log($serial, ProductSerialMovement::MOVEMENT_DAMAGE, null, null, ['remarks' => $remarks]);
        });
    }

    public function recordLost(ProductSerial $serial, ?string $remarks = null): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $remarks) {
            $serial->forceFill(['status' => ProductSerial::STATUS_LOST])->save();
            return $this->log($serial, ProductSerialMovement::MOVEMENT_LOST, null, null, ['remarks' => $remarks]);
        });
    }

    // ── Internal: append-only audit write ──────────────────────────────────

    protected function log(ProductSerial $serial, string $type, ?string $refType, ?int $refId, array $extras = []): ProductSerialMovement
    {
        return ProductSerialMovement::create(array_merge([
            'product_serial_id' => $serial->id,
            'movement_type'     => $type,
            'reference_type'    => $refType,
            'reference_id'      => $refId,
            'created_by'        => Auth::id(),
            'created_at'        => now(),
        ], $extras));
    }
}
