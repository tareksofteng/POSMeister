<?php

namespace App\Modules\BusinessInsights\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Model;

class BusinessInsight extends Model
{
    protected $table = 'business_insights';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_IGNORED  = 'ignored';
    public const STATUS_PINNED   = 'pinned';

    protected $fillable = [
        'code', 'period_key', 'kind', 'severity', 'confidence',
        'title', 'detail', 'meta', 'action',
        'branch_id', 'audience_role',
        'status', 'observed_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'meta'        => 'array',
        'action'      => 'array',
        'confidence'  => 'integer',
        'observed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function branch()    { return $this->belongsTo(Branch::class); }
    public function resolver()  { return $this->belongsTo(User::class, 'resolved_by'); }

    public function scopeActive($q)   { return $q->where('status', self::STATUS_ACTIVE); }
    public function scopePinned($q)   { return $q->where('status', self::STATUS_PINNED); }
    public function scopeOfKind($q, string $kind) { return $q->where('kind', $kind); }
}
