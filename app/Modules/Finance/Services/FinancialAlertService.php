<?php

namespace App\Modules\Finance\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Finance\Models\Budget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinancialAlertService
{
    public function __construct(
        private readonly BudgetAnalyticsService $analytics,
    ) {}

    /**
     * Returns a flat list of currently active alerts, severity-ordered.
     * Severities: critical | warning | info.
     */
    public function active(?int $branchId = null): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->budgetAlerts($branchId));
        $alerts = array_merge($alerts, $this->expenseSpikeAlert($branchId));
        $alerts = array_merge($alerts, $this->overdueRecurringAlerts($branchId));

        usort($alerts, fn($a, $b) => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']));

        return $alerts;
    }

    private function budgetAlerts(?int $branchId): array
    {
        $q = Budget::with(['items.category:id,name'])
            ->where('status', 'active');
        if (!$this->isAdmin() && Auth::user()?->branch_id) {
            $q->where(function ($qq) {
                $qq->where('branch_id', Auth::user()->branch_id)->orWhereNull('branch_id');
            });
        } elseif ($branchId) {
            $q->where('branch_id', $branchId);
        }

        $alerts = [];
        foreach ($q->get() as $budget) {
            $analysis = $this->analytics->analyze($budget);
            $totalPct = $analysis['totals']['percent_used'];

            if ($analysis['totals']['overspent']) {
                $alerts[] = $this->alert(
                    'critical',
                    'budget_exceeded',
                    "Budget \"{$budget->title}\" überschritten ({$totalPct}%)",
                    $analysis['totals']['total_actual'] - $analysis['totals']['total_allocated'],
                    ['budget_id' => $budget->id]
                );
            } elseif ($totalPct >= 90) {
                $alerts[] = $this->alert(
                    'critical',
                    'budget_near_limit',
                    "Budget \"{$budget->title}\" zu {$totalPct}% verbraucht",
                    null,
                    ['budget_id' => $budget->id]
                );
            } elseif ($totalPct >= $budget->warning_threshold_percent) {
                $alerts[] = $this->alert(
                    'warning',
                    'budget_warning',
                    "Budget \"{$budget->title}\" hat die Warnschwelle erreicht ({$totalPct}%)",
                    null,
                    ['budget_id' => $budget->id]
                );
            }

            // Per-category over-spend
            foreach ($analysis['categories'] as $cat) {
                if ($cat['overspent']) {
                    $alerts[] = $this->alert(
                        'warning',
                        'category_overspent',
                        "Kategorie \"{$cat['category_name']}\" überschritten ({$cat['percent']}%)",
                        $cat['actual'] - $cat['allocated'],
                        ['budget_id' => $budget->id, 'category_id' => $cat['expense_category_id']]
                    );
                }
            }
        }
        return $alerts;
    }

    private function expenseSpikeAlert(?int $branchId): array
    {
        // Compare this month's spend to the average of the prior 3 months
        $now      = Carbon::today();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd   = $now->copy()->endOfMonth();
        $priorEnd   = $monthStart->copy()->subDay();
        $priorStart = $priorEnd->copy()->subMonthsNoOverflow(2)->startOfMonth();

        $thisMonth = $this->scopedExpenses($branchId)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        $priorTotal = $this->scopedExpenses($branchId)
            ->whereBetween('expense_date', [$priorStart, $priorEnd])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        $priorAvg = $priorTotal / 3.0;

        if ($priorAvg <= 0 || $thisMonth <= 0) {
            return [];
        }

        $deltaPct = ($thisMonth - $priorAvg) / $priorAvg * 100;

        if ($deltaPct >= 30) {
            return [$this->alert(
                'warning',
                'expense_spike',
                'Ausgaben diesen Monat ' . round($deltaPct, 0) . '% über dem 3-Monats-Schnitt',
                (float) $thisMonth - $priorAvg,
                ['this_month' => round($thisMonth, 2), 'prior_avg' => round($priorAvg, 2)]
            )];
        }
        return [];
    }

    private function overdueRecurringAlerts(?int $branchId): array
    {
        $q = Expense::query()
            ->where('is_recurring', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', Carbon::today()->toDateString());

        if ($branchId) $q->where('branch_id', $branchId);
        if (!$this->isAdmin() && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }

        return $q->get(['id', 'expense_number', 'title', 'next_due_date'])
            ->map(fn($e) => $this->alert(
                'warning',
                'recurring_overdue',
                "Wiederkehrende Ausgabe \"{$e->title}\" ist überfällig (fällig: " . $e->next_due_date->format('d.m.Y') . ")",
                null,
                ['expense_id' => $e->id, 'number' => $e->expense_number]
            ))
            ->all();
    }

    private function scopedExpenses(?int $branchId)
    {
        $q = Expense::query();
        if ($branchId) $q->where('branch_id', $branchId);
        if (!$this->isAdmin() && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }
        return $q;
    }

    private function alert(string $severity, string $type, string $message, ?float $amount, array $context = []): array
    {
        return [
            'severity' => $severity,
            'type'     => $type,
            'message'  => $message,
            'amount'   => $amount !== null ? round($amount, 2) : null,
            'context'  => $context,
        ];
    }

    private function severityWeight(string $severity): int
    {
        return ['info' => 0, 'warning' => 1, 'critical' => 2][$severity] ?? 0;
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
