<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name', 'price_monthly', 'currency',
        'max_branches', 'max_users', 'max_products', 'max_invoices_per_month',
        'features', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features'       => 'array',
        'is_active'      => 'boolean',
        'price_monthly'  => 'decimal:2',
    ];

    public function scopeActive($q) { return $q->where('is_active', true); }
}
