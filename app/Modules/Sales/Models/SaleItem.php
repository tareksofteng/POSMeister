<?php

namespace App\Modules\Sales\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'quantity',
        'unit_price', 'cost_price', 'tax_rate',
        'vat_amount', 'line_total', 'is_service',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_service' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
