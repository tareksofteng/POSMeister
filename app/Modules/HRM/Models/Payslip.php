<?php

namespace App\Modules\HRM\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payslip extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'payslip_number',
        'payroll_period_id', 'employee_id', 'branch_id',
        'period_start', 'period_end',
        'days_in_period', 'days_worked', 'days_absent', 'days_leave', 'days_late', 'days_half',
        'basic_salary',
        'total_allowances', 'total_bonuses', 'total_overtime',
        'total_deductions', 'tax_amount',
        'gross_salary', 'net_salary',
        'paid_amount', 'payment_date', 'payment_method', 'payment_reference',
        'status', 'notes',
    ];

    protected $casts = [
        'period_start'     => 'date',
        'period_end'       => 'date',
        'payment_date'     => 'date',
        'basic_salary'     => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_bonuses'    => 'decimal:2',
        'total_overtime'   => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'gross_salary'     => 'decimal:2',
        'net_salary'       => 'decimal:2',
        'paid_amount'      => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
