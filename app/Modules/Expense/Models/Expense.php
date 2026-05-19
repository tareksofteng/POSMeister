<?php

namespace App\Modules\Expense\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'expense_number',
        'branch_id', 'expense_category_id',
        'title', 'description',
        'amount', 'expense_date',
        'payment_method', 'reference_no',
        'attachment', 'status',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'paid_by', 'paid_at',
        'is_recurring', 'recurring_frequency', 'next_due_date', 'recurring_end_date',
        'parent_expense_id',
    ];

    protected $casts = [
        'expense_date'       => 'date',
        'amount'             => 'decimal:2',
        'approved_at'        => 'datetime',
        'rejected_at'        => 'datetime',
        'paid_at'            => 'datetime',
        'is_recurring'       => 'boolean',
        'next_due_date'      => 'date',
        'recurring_end_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ExpenseAuditLog::class)->orderByDesc('created_at');
    }

    public function isPaid(): bool     { return $this->status === 'paid'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
