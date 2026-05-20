<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'account_code', 'account_name',
        'account_type', 'normal_balance',
        'parent_id', 'branch_id',
        'allow_manual_entry', 'is_system', 'is_active',
        'description',
    ];

    protected $casts = [
        'allow_manual_entry' => 'boolean',
        'is_system'          => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function scopeActive($q)        { return $q->where('is_active', true); }
    public function scopeOfType($q, $type) { return $q->where('account_type', $type); }

    public function isDebitNormal(): bool  { return $this->normal_balance === 'debit'; }
    public function isCreditNormal(): bool { return $this->normal_balance === 'credit'; }
}
