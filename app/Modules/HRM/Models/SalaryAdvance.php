<?php

namespace App\Modules\HRM\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdvance extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'employee_id', 'branch_id',
        'granted_on', 'amount', 'deducted_amount',
        'status', 'settled_in_payslip_id',
        'reason', 'notes',
    ];

    protected $casts = [
        'granted_on'      => 'date',
        'amount'          => 'decimal:2',
        'deducted_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function settledPayslip(): BelongsTo { return $this->belongsTo(Payslip::class, 'settled_in_payslip_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    public function isOutstanding(): bool
    {
        return in_array($this->status, ['outstanding', 'partially_deducted'], true);
    }

    public function outstandingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->deducted_amount);
    }

    public function scopeOutstanding($q)
    {
        return $q->whereIn('status', ['outstanding', 'partially_deducted']);
    }
}
