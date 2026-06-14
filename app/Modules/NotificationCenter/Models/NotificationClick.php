<?php

namespace App\Modules\NotificationCenter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationClick extends Model
{
    protected $table = 'notification_clicks';

    protected $fillable = [
        'notification_id', 'user_id', 'subscription_id',
        'code', 'action', 'dismissed', 'clicked_at',
    ];

    protected $casts = [
        'dismissed'  => 'boolean',
        'clicked_at' => 'datetime',
    ];

    public function notification() { return $this->belongsTo(SmartNotification::class, 'notification_id'); }
    public function user()         { return $this->belongsTo(User::class); }
    public function subscription() { return $this->belongsTo(PushSubscription::class, 'subscription_id'); }
}
