<?php

namespace App\Modules\OMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'template_id', 'channel',
        'recipient_type', 'recipient_id', 'recipient_address',
        'subject', 'body', 'payload',
        'reference_type', 'reference_id',
        'status', 'sent_at', 'read_at',
        'attempts', 'last_error',
        'created_by',
    ];

    protected $casts = [
        'payload'  => 'array',
        'sent_at'  => 'datetime',
        'read_at'  => 'datetime',
        'attempts' => 'integer',
    ];

    public function template(): BelongsTo { return $this->belongsTo(NotificationTemplate::class, 'template_id'); }
}
