<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringExpenseService
{
    /**
     * For every active recurring template whose next_due_date is on or before
     * today, create a new pending expense from the template and advance the
     * template's next_due_date by its frequency. Returns how many were spawned.
     */
    public function generateDue(?Carbon $asOf = null): int
    {
        $today = ($asOf ?? Carbon::today())->toDateString();
        $created = 0;

        $templates = Expense::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurring_frequency')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', $today)
            ->whereNull('parent_expense_id')
            ->get();

        foreach ($templates as $template) {
            DB::transaction(function () use ($template, &$created) {
                while ($template->next_due_date && $template->next_due_date->lte(Carbon::today())) {
                    if ($template->recurring_end_date && $template->next_due_date->gt($template->recurring_end_date)) {
                        $template->update(['is_recurring' => false]);
                        break;
                    }

                    $copy = $template->replicate([
                        'expense_number', 'is_recurring', 'recurring_frequency',
                        'next_due_date', 'recurring_end_date',
                        'approved_by', 'approved_at',
                        'rejected_by', 'rejected_at', 'rejection_reason',
                        'paid_by', 'paid_at',
                    ]);
                    $copy->expense_number    = $this->nextNumber();
                    $copy->expense_date      = $template->next_due_date;
                    $copy->status            = 'pending';
                    $copy->parent_expense_id = $template->id;
                    $copy->is_recurring      = false;
                    $copy->save();

                    ExpenseAuditLog::create([
                        'expense_id' => $copy->id,
                        'user_id'    => null,
                        'action'     => 'created',
                        'notes'      => 'Automatisch aus wiederkehrender Vorlage erstellt',
                        'created_at' => now(),
                    ]);

                    $created++;
                    $template->next_due_date = $this->advance(
                        $template->next_due_date,
                        $template->recurring_frequency
                    );
                }

                $template->save();
            });
        }

        return $created;
    }

    private function advance(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly'  => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'yearly'  => $date->copy()->addYear(),
            default   => $date,
        };
    }

    private function nextNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = "EXP-{$year}-";
        $last   = Expense::withTrashed()
            ->where('expense_number', 'like', $prefix . '%')
            ->max('expense_number');
        $next = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
