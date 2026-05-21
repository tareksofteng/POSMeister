<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'token_id',
        'device', 'browser', 'platform',
        'ip', 'city', 'country',
        'first_seen_at', 'last_seen_at', 'revoked_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'revoked_at'    => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function scopeActive($q) { return $q->whereNull('revoked_at'); }
}
