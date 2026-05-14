<?php

namespace App\Modules\HRM\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id', 'branch_id', 'attendance_date',
        'status', 'check_in', 'check_out', 'worked_minutes',
        'shift_id', 'is_late', 'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'is_late'         => 'boolean',
        'worked_minutes'  => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
