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
        'is_active',
    ];

    protected $casts = [
        'cost_price'       => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'wholesale_price'  => 'decimal:2',
        'min_selling_price' => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'is_service'       => 'boolean',
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
