<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cashbook extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name', 'branch_id', 'coa_account_id',
        'opening_balance', 'opening_date', 'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date'    => 'date',
        'is_active'       => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_account_id');
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
