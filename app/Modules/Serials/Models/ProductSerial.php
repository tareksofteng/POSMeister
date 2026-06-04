<?php

namespace App\Modules\Serials\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 |--------------------------------------------------------------------------
 | ProductSerial — one row per physical device
 |--------------------------------------------------------------------------
 |
 | Status transitions (see SerialMovementService for the only place
 | that should ever mutate `status`):
 |
 |   in_stock ─┬─→ sold ────────→ sales_returned ─→ in_stock
 |             ├─→ reserved ──→ unreserve → in_stock
 |             ├─→ purchase_returned (terminal for this lifecycle)
 |             ├─→ damaged
 |             └─→ lost
 |
 | Status is a plain enum on the DB so reporting is fast — but the
 | business rule "you may only move from in_stock to sold" lives in
 | SerialTrackingService, not on this model.
 */
class ProductSerial extends Model
{
    use HasAuditFields, SoftDeletes;

    public const STATUS_IN_STOCK          = 'in_stock';
    public const STATUS_SOLD              = 'sold';
    public const STATUS_PURCHASE_RETURNED = 'purchase_returned';
    public const STATUS_SALES_RETURNED    = 'sales_returned';
    public const STATUS_RESERVED          = 'reserved';
    public const STATUS_DAMAGED           = 'damaged';
    public const STATUS_LOST              = 'lost';

    protected $fillable = [
        'product_id', 'branch_id', 'serial_number',
        'purchase_id', 'purchase_item_id', 'supplier_id',
        'sale_id', 'sale_item_id', 'customer_id',
        'purchase_return_id', 'sales_return_id',
        'status',
        'purchase_date', 'sale_date',
        'warranty_months', 'warranty_expiry_date',
        'notes',
    ];

    protected $casts = [
        'purchase_date'        => 'date',
        'sale_date'            => 'date',
        'warranty_expiry_date' => 'date',
        'warranty_months'      => 'integer',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Product\Models\Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Branch\Models\Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductSerialMovement::class)->orderByDesc('created_at');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** Sellable units only (used by inventory count). */
    public function scopeInStock($q)
    {
        return $q->where('status', self::STATUS_IN_STOCK);
    }

    public function scopeForBranch($q, $branchId)
    {
        return $branchId ? $q->where('branch_id', $branchId) : $q;
    }

    public function scopeWarrantyExpiringWithinDays($q, int $days)
    {
        $today  = now()->toDateString();
        $future = now()->addDays($days)->toDateString();
        return $q->whereNotNull('warranty_expiry_date')
                 ->whereBetween('warranty_expiry_date', [$today, $future]);
    }

    // ── Derived helpers ────────────────────────────────────────────────────

    /** Days remaining on warranty; negative means expired; null if no warranty. */
    public function warrantyRemainingDays(): ?int
    {
        if (!$this->warranty_expiry_date) return null;
        return (int) now()->startOfDay()->diffInDays($this->warranty_expiry_date, false);
    }

    public function isUnderWarranty(): bool
    {
        $days = $this->warrantyRemainingDays();
        return $days !== null && $days >= 0;
    }
}
