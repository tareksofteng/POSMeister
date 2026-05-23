<?php

namespace App\Modules\NotificationCenter\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id', 'muted_categories', 'min_severity', 'quiet_hours', 'channels', 'digest',
    ];

    protected $casts = [
        'muted_categories' => 'array',
        'quiet_hours'      => 'array',
        'channels'         => 'array',
        'digest'           => 'array',
    ];

    public static function defaults(): array
    {
        return [
            'muted_categories' => [],
            'min_severity'     => 'info',
            'quiet_hours'      => null,
            'channels'         => ['in_app' => true, 'email' => false],
            'digest'           => ['daily' => true, 'weekly' => false],
        ];
    }
}
