<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_number', 'quotation_date', 'valid_until', 'branch_id',
        'customer_id', 'customer_name', 'customer_phone', 'customer_email', 'customer_address',
        'quotation_type',
        'subtotal', 'discount_amount', 'vat_amount', 'freight_amount', 'grand_total',
        'terms', 'note', 'status', 'converted_sale_id', 'created_by',
    ];

    protected $casts = [
        'quotation_date'  => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount'      => 'decimal:2',
        'freight_amount'  => 'decimal:2',
        'grand_total'     => 'decimal:2',
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
        return $this->hasMany(QuotationItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function getDisplayCustomerNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->customer_name ?? '—';
    }
}
