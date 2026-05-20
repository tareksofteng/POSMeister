<?php

namespace App\Modules\OMS\Models;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id',
        'quantity', 'fulfilled_qty',
        'unit_price', 'cost_price', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity'      => 'decimal:2',
        'fulfilled_qty' => 'decimal:2',
        'unit_price'    => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'tax_rate'      => 'decimal:2',
        'line_total'    => 'decimal:2',
    ];

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
