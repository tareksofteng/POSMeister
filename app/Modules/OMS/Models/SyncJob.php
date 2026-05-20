<?php

namespace App\Modules\OMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncJob extends Model
{
    protected $fillable = [
        'connector_id', 'entity', 'direction', 'status',
        'started_at', 'finished_at',
        'records_processed', 'records_failed',
        'error', 'summary', 'created_by',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'summary'     => 'array',
    ];

    public function connector(): BelongsTo { return $this->belongsTo(EcommerceConnector::class, 'connector_id'); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
}
