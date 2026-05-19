<?php

namespace App\Modules\Finance\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Finance\Models\Budget;
use App\Modules\HRM\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinancialCalendarService
{
    /**
     * Returns calendar events for a given month: recurring expense due dates,
     * payroll period ends, fiscal-year ends, and high-amount paid expenses.
     */
    public function month(int $year, int $month, ?int $branchId = null): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $events = collect();

        // Recurring expense due dates
        $recurring = Expense::query()
            ->where('is_recurring', true)
            ->whereBetween('next_due_date', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin() && Auth::user()?->branch_id, fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->get(['id', 'title', 'amount', 'next_due_date', 'expense_number']);

        foreach ($recurring as $e) {
            $events->push([
                'date'    => $e->next_due_date->format('Y-m-d'),
                'type'    => 'recurring_due',
                'tone'    => 'indigo',
                'title'   => $e->title,
                'amount'  => (float) $e->amount,
                'ref'     => $e->expense_number,
                'context' => ['expense_id' => $e->id],
            ]);
        }

        // High-amount paid expenses (top 25%) within the month
        $paid = Expense::query()
            ->where('status', 'paid')
            ->whereBetween('expense_date', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin() && Auth::user()?->branch_id, fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->get(['id', 'title', 'amount', 'expense_date', 'expense_number'])
            ->sortByDesc('amount')
            ->values();

        if ($paid->count() > 0) {
            $threshold = $paid->take(max(1, (int) ceil($paid->count() / 4)))->min('amount');
            foreach ($paid as $e) {
                if ((float) $e->amount < (float) $threshold) continue;
                $events->push([
                    'date'    => $e->expense_date->format('Y-m-d'),
                    'type'    => 'high_expense',
                    'tone'    => 'rose',
                    'title'   => $e->title,
                    'amount'  => (float) $e->amount,
                    'ref'     => $e->expense_number,
                    'context' => ['expense_id' => $e->id],
                ]);
            }
        }

        // Payroll period ends
        $payrollPeriods = PayrollPeriod::query()
            ->whereBetween('period_end', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get(['id', 'label', 'period_end']);

        foreach ($payrollPeriods as $p) {
            $events->push([
                'date'    => $p->period_end->format('Y-m-d'),
                'type'    => 'payroll_period',
                'tone'    => 'emerald',
                'title'   => $p->label,
                'amount'  => null,
                'ref'     => null,
                'context' => ['period_id' => $p->id],
            ]);
        }

        // Budget cycle ends
        $budgets = Budget::query()
            ->whereBetween('end_date', [$start, $end])
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get(['id', 'title', 'end_date', 'total_budget']);

        foreach ($budgets as $b) {
            $events->push([
                'date'    => $b->end_date->format('Y-m-d'),
                'type'    => 'budget_end',
                'tone'    => 'amber',
                'title'   => $b->title,
                'amount'  => (float) $b->total_budget,
                'ref'     => null,
                'context' => ['budget_id' => $b->id],
            ]);
        }

        $grouped = $events
            ->groupBy('date')
            ->map(fn($list, $date) => [
                'date'   => $date,
                'events' => $list->values(),
            ])
            ->values();

        return [
            'year'  => $year,
            'month' => $month,
            'days'  => $grouped,
        ];
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
