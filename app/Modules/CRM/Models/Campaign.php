<?php

namespace App\Modules\CRM\Models;

use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name', 'type', 'status',
        'message_body', 'audience_filter', 'settings',
        'scheduled_at', 'sent_at',
        'recipients_count', 'delivered_count',
        'branch_id',
    ];

    protected $casts = [
        'audience_filter'  => 'array',
        'settings'         => 'array',
        'scheduled_at'     => 'datetime',
        'sent_at'          => 'datetime',
        'recipients_count' => 'integer',
        'delivered_count'  => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopePending($q)
    {
        return $q->whereIn('status', ['draft', 'scheduled', 'queued']);
    }
}
