<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'status',
        'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at',
        'overrides',
    ];

    protected $casts = [
        'starts_at'     => 'date',
        'ends_at'       => 'date',
        'trial_ends_at' => 'date',
        'cancelled_at'  => 'date',
        'overrides'     => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo   { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
}
