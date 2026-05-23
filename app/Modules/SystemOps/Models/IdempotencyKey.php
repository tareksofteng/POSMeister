<?php

namespace App\Modules\SystemOps\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key', 'entity_type', 'entity_id', 'response_status',
        'response_hash', 'actor_id', 'actor_ip', 'created_at',
    ];

    protected $casts = [
        'entity_id'       => 'integer',
        'response_status' => 'integer',
        'created_at'      => 'datetime',
    ];
}
