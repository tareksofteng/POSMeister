<?php

namespace App\Modules\OMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_id', 'triggered_at', 'status',
        'matched_count', 'action_result', 'error',
    ];

    protected $casts = [
        'triggered_at'  => 'datetime',
        'action_result' => 'array',
    ];

    public function rule(): BelongsTo { return $this->belongsTo(AutomationRule::class, 'rule_id'); }
}
