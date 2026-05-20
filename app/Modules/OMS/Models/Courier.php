<?php

namespace App\Modules\OMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code',
        'api_endpoint', 'api_key', 'api_secret',
        'supported_regions', 'settings',
        'is_active',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'supported_regions' => 'array',
        'settings'          => 'array',
    ];

    protected $hidden = ['api_key', 'api_secret'];

    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
