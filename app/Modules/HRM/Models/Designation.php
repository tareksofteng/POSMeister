<?php

namespace App\Modules\HRM\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'department_id', 'title', 'hierarchy_level', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'hierarchy_level' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
