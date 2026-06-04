<?php

namespace App\Modules\Product\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasAuditFields, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'image',
        'category_id',
        'brand_id',
        'unit_id',
        'barcode',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'min_selling_price',
        'tax_rate',
        'reorder_level',
        'is_service',
        'is_serialized',
        'is_active',
    ];

    protected $casts = [
        'cost_price'       => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'wholesale_price'  => 'decimal:2',
        'min_selling_price' => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'is_service'       => 'boolean',
        'is_serialized'    => 'boolean',
        'is_active'        => 'boolean',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\ProductFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Phase Y — serial / IMEI / warranty tracking.
     *
     * Every physical device for a serialized product lives in
     * product_serials. The relation is loaded eagerly only when the UI
     * actually needs it (Serial Inventory modal, Customer "Owned Devices"
     * tab); the rest of the app treats it like any other HasMany.
     */
    public function serials(): HasMany
    {
        return $this->hasMany(\App\Modules\Serials\Models\ProductSerial::class);
    }

    /**
     * Sellable serialized units only — used by the inventory dashboard so
     * a serialized product's "on-hand quantity" is derived from real
     * serials in stock, never from a hand-maintained counter.
     */
    public function inStockSerials(): HasMany
    {
        return $this->serials()->where('status', \App\Modules\Serials\Models\ProductSerial::STATUS_IN_STOCK);
    }

    /**
     * Once a product has any serial history (purchase or sale movement),
     * the is_serialized flag is frozen — flipping it after the fact would
     * leave the inventory in an inconsistent state.
     *
     * Used by:
     *   - StoreProductRequest / UpdateProductRequest (validation)
     *   - ProductFormModal.vue (checkbox disabled state + tooltip)
     */
    public function isSerializationLocked(): bool
    {
        return $this->serials()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getProfitMarginAttribute(): float
    {
        if ((float) $this->selling_price === 0.0) return 0.0;
        return round((($this->selling_price - $this->cost_price) / $this->selling_price) * 100, 2);
    }
}
