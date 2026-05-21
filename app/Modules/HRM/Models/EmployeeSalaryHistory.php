<?php

namespace App\Modules\HRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryHistory extends Model
{
    public $timestamps = false;
    protected $table = 'employee_salary_history';

    protected $fillable = [
        'employee_id',
        'previous_salary', 'new_salary', 'delta',
        'effective_date', 'reason',
        'changed_by', 'created_at',
    ];

    protected $casts = [
        'effective_date'  => 'date',
        'previous_salary' => 'decimal:2',
        'new_salary'      => 'decimal:2',
        'delta'           => 'decimal:2',
        'created_at'      => 'datetime',
    ];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
