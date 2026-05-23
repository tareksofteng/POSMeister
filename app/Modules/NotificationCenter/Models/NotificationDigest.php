<?php

namespace App\Modules\NotificationCenter\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDigest extends Model
{
    protected $table = 'notification_digests';

    protected $fillable = ['user_id', 'period', 'for_date', 'summary', 'delivered_at'];

    protected $casts = [
        'summary'      => 'array',
        'for_date'     => 'date',
        'delivered_at' => 'datetime',
    ];
}
