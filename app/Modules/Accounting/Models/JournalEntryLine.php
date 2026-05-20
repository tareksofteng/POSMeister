<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'account_id', 'branch_id',
        'debit', 'credit', 'entry_date', 'narration', 'line_no',
    ];

    protected $casts = [
        'debit'      => 'decimal:2',
        'credit'     => 'decimal:2',
        'entry_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
