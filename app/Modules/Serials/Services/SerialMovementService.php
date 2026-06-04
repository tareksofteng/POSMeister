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
        return DB::transaction(function () use ($serial, $refType, $refId, $customerId, $saleItemId) {
            $serial->forceFill([
                'status'        => ProductSerial::STATUS_SOLD,
                'sale_id'       => $refId,
                'sale_item_id'  => $saleItemId,
                'customer_id'   => $customerId,
                'sale_date'     => now()->toDateString(),
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_SALE, $refType, $refId);
        });
    }

    /** Customer returned the device. Default disposition is back in stock. */
    public function recordSalesReturn(ProductSerial $serial, string $refType, int $refId, bool $resellable = true): ProductSerialMovement
    {
        return DB::transaction(function () use ($serial, $refType, $refId, $resellable) {
            $serial->forceFill([
                'status'           => $resellable ? ProductSerial::STATUS_IN_STOCK : ProductSerial::STATUS_SALES_RETURNED,
                'sales_return_id'  => $refId,
            ])->save();

            return $this->log($serial, ProductSerialMovement::MOVEMENT_SALES_RETURN, $refType, $refId);
        });
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
