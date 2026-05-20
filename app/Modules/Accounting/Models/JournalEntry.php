<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $fillable = [
        'entry_number', 'entry_date', 'branch_id',
        'reference_type', 'reference_id', 'reference_number',
        'narration',
        'total_debit', 'total_credit',
        'status', 'posted_at', 'posted_by',
        'reversed_by_entry_id',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'posted_at'    => 'datetime',
        'total_debit'  => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_no');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversingEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isPosted(): bool   { return $this->status === 'posted'; }
    public function isReversed(): bool { return $this->status === 'reversed'; }

    public function isBalanced(): bool
    {
        return (float) $this->total_debit === (float) $this->total_credit;
    }
}
