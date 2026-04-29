<?php

namespace App\Modules\Sales\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'phone', 'email', 'address',
        'customer_type', 'credit_limit', 'is_active', 'branch_id',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Total outstanding due = sum of sale dues − sum of recorded payments
    public function getCurrentDueAttribute(): float
    {
        $saleDues = $this->sales()->where('status', 'active')->sum('due_amount');
        $paid     = $this->payments()->sum('amount');
        return max(0, (float) $saleDues - (float) $paid);
    }
}
