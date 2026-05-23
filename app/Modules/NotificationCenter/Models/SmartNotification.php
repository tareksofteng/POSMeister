<?php

namespace App\Modules\NotificationCenter\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SmartNotification extends Model
{
    protected $table = 'smart_notifications';

    protected $fillable = [
        'category', 'code', 'severity', 'urgency',
        'title', 'message', 'actions', 'meta',
        'entity_type', 'entity_id',
        'audience_user_id', 'audience_role', 'branch_id',
        'dedupe_key', 'cooldown_until', 'escalation_level',
        'expires_at', 'read_at', 'acked_at', 'archived_at', 'acked_by',
    ];

    protected $casts = [
        'actions'          => 'array',
        'meta'             => 'array',
        'urgency'          => 'integer',
        'escalation_level' => 'integer',
        'cooldown_until'   => 'datetime',
        'expires_at'       => 'datetime',
        'read_at'          => 'datetime',
        'acked_at'         => 'datetime',
        'archived_at'      => 'datetime',
    ];

    public function audience() { return $this->belongsTo(User::class, 'audience_user_id'); }
    public function ackedBy()  { return $this->belongsTo(User::class, 'acked_by'); }

    public function scopeActive($q)
    {
        return $q->whereNull('archived_at')
                 ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); });
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function scopeForUser($q, int $userId, ?string $role = null)
    {
        return $q->where(function ($q) use ($userId, $role) {
            $q->where('audience_user_id', $userId);
            if ($role) $q->orWhere('audience_role', $role);
        });
    }
}
