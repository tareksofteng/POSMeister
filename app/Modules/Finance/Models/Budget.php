<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'title', 'fiscal_year', 'start_date', 'end_date',
        'branch_id', 'total_budget',
        'warning_threshold_percent', 'status', 'notes',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'start_date'  => 'date',
        'end_date'    => 'date',
        'total_budget'=> 'decimal:2',
        'warning_threshold_percent' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
