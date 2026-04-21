<?php

namespace App\Modules\Purchase\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'purchase_number', 'branch_id', 'supplier_id', 'purchase_date',
        'status', 'reference', 'notes',
        'subtotal', 'discount_amount', 'vat_amount', 'freight_amount', 'total_amount',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount'      => 'decimal:2',
        'freight_amount'  => 'decimal:2',
        'total_amount'    => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
