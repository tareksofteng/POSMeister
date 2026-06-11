<?php

namespace App\Modules\NotificationCenter\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id', 'branch_id',
        'endpoint', 'p256dh_key', 'auth_token',
        'browser', 'platform', 'device_type', 'label',
        'is_active', 'last_seen_at',
        'failure_count', 'last_failed_at', 'last_failure_reason',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_seen_at'   => 'datetime',
        'last_failed_at' => 'datetime',
        'failure_count'  => 'integer',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function branch() { return $this->belongsTo(Branch::class); }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Shape the row for minishlink/web-push (or any RFC8030 client).
     */
    public function toWebPushPayload(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->p256dh_key,
                'auth'   => $this->auth_token,
            ],
        ];
    }

    /**
     * Record a successful send. Bumps last_seen_at and clears any
     * accumulated failure state so a transient outage doesn't stick
     * around once delivery recovers.
     */
    public function markDelivered(): void
    {
        $this->update([
            'last_seen_at'        => now(),
            'failure_count'       => 0,
            'last_failed_at'      => null,
            'last_failure_reason' => null,
        ]);
    }

    /**
     * Permanent failure (404 / 410): the device is gone, never coming back.
     */
    public function markGone(string $reason): void
    {
        $this->update([
            'is_active'           => false,
            'last_failed_at'      => now(),
            'last_failure_reason' => substr($reason, 0, 200),
        ]);
    }

    /**
     * Transient failure (network blip, 5xx). Increment the counter and
     * after a handful of consecutive failures retire the subscription so
     * we don't slowly leak retry budget.
     */
    public function markTransientFailure(string $reason): void
    {
        $this->increment('failure_count');
        $this->update([
            'last_failed_at'      => now(),
            'last_failure_reason' => substr($reason, 0, 200),
        ]);
        if ($this->failure_count >= 8) {
            $this->update(['is_active' => false]);
        }
    }
}
