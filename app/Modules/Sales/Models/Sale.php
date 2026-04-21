<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sale_number', 'sale_date', 'branch_id',
        'customer_id', 'customer_name', 'customer_phone', 'customer_address', 'customer_type',
        'sale_type',
        'subtotal', 'discount_amount', 'vat_amount', 'freight_amount', 'grand_total',
        'cash_paid', 'card_paid', 'total_paid', 'due_amount', 'previous_due',
        'note', 'status', 'created_by', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'sale_date'       => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount'      => 'decimal:2',
        'freight_amount'  => 'decimal:2',
        'grand_total'     => 'decimal:2',
        'cash_paid'       => 'decimal:2',
        'card_paid'       => 'decimal:2',
        'total_paid'      => 'decimal:2',
        'due_amount'      => 'decimal:2',
        'previous_due'    => 'decimal:2',
        'cancelled_at'    => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDisplayCustomerNameAttribute(): string
    {
        if ($this->customer_type === 'registered' && $this->customer) {
            return $this->customer->name;
        }
        return $this->customer_name ?? 'Laufkunde';
    }
}
