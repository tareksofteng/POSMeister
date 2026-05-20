<?php

namespace App\Modules\OMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcommerceConnector extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'type',
        'api_url', 'api_key', 'api_secret',
        'settings', 'is_active', 'last_sync_at',
    ];

    protected $casts = [
        'settings'     => 'array',
        'is_active'    => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = ['api_key', 'api_secret'];

    public function jobs(): HasMany { return $this->hasMany(SyncJob::class, 'connector_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
