<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Expense\Models\Expense;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpenseAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged('status') && $expense->status === 'paid') {
            $this->tryPost($expense);
        }
    }

    public function created(Expense $expense): void
    {
        if ($expense->status === 'paid') {
            $this->tryPost($expense);
        }
    }

    private function tryPost(Expense $expense): void
    {
        try {
            $this->posting->postExpense($expense);
        } catch (Throwable $e) {
            Log::error('Expense auto-posting failed', [
                'expense_id' => $expense->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
