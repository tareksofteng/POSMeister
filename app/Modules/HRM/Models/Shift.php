<?php

namespace App\Modules\HRM\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_minutes', 'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'grace_minutes' => 'integer',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5);
    }
}
