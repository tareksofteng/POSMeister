<?php

namespace App\Modules\Quotation\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'quote_number', 'quote_date', 'valid_until', 'branch_id',
        'customer_id', 'customer_name', 'customer_phone',
        'status', 'note',
        'subtotal', 'discount_amount', 'vat_amount', 'grand_total',
        'sale_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quote_date'      => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount'      => 'decimal:2',
        'grand_total'     => 'decimal:2',
    ];

    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function sale(): BelongsTo      { return $this->belongsTo(Sale::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany       { return $this->hasMany(QuotationItem::class); }

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isInvoiced(): bool { return $this->status === 'invoiced'; }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}
