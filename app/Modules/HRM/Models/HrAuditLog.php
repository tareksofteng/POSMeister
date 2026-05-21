<?php

namespace App\Modules\HRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAuditLog extends Model
{
    public $timestamps = false;
    protected $table = 'hr_audit_logs';

    protected $fillable = [
        'action', 'entity_type', 'entity_id',
        'before', 'after', 'note',
        'actor_id', 'actor_ip', 'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
