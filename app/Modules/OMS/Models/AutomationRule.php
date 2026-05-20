<?php

namespace App\Modules\OMS\Models;

use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationRule extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name', 'trigger',
        'condition', 'action_type', 'action_config',
        'is_active', 'last_run_at', 'run_count', 'match_count',
        'branch_id',
    ];

    protected $casts = [
        'condition'     => 'array',
        'action_config' => 'array',
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
    ];

    public function logs(): HasMany    { return $this->hasMany(AutomationLog::class, 'rule_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
