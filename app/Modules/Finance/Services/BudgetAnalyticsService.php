<?php

namespace App\Modules\Finance\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Finance\Models\Budget;
use Carbon\Carbon;

class BudgetAnalyticsService
{
    public function analyze(Budget $budget): array
    {
        $budget->loadMissing(['items.category:id,name,code', 'branch:id,name']);

        $start = $budget->start_date;
        $end   = $budget->end_date;

        // Actual spend per category (we count anything that's been approved or paid)
        $actualByCategory = Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->whereIn('status', ['approved', 'paid'])
            ->when($budget->branch_id, fn($q) => $q->where('branch_id', $budget->branch_id))
            ->selectRaw('expense_category_id, sum(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $categories = $budget->items->map(function ($item) use ($actualByCategory, $budget) {
            $allocated = (float) $item->allocated_amount;
            $actual    = (float) ($actualByCategory[$item->expense_category_id] ?? 0);
            $remaining = $allocated - $actual;
            $percent   = $allocated > 0 ? round($actual / $allocated * 100, 1) : 0;

            return [
                'expense_category_id' => $item->expense_category_id,
                'category_name'       => $item->category?->name,
                'category_code'       => $item->category?->code,
                'allocated'           => round($allocated, 2),
                'actual'              => round($actual, 2),
                'remaining'           => round($remaining, 2),
                'percent'             => $percent,
                'health'              => $this->health($percent, $budget->warning_threshold_percent),
                'overspent'           => $actual > $allocated,
            ];
        });

        $totalAllocated = (float) $budget->items->sum('allocated_amount');
        $totalActual    = (float) $categories->sum('actual');
        $totalRemaining = $totalAllocated - $totalActual;
        $totalPercent   = $totalAllocated > 0 ? round($totalActual / $totalAllocated * 100, 1) : 0;

        // Monthly burn: month-by-month spend over the budget period
        $burn = Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->whereIn('status', ['approved', 'paid'])
            ->when($budget->branch_id, fn($q) => $q->where('branch_id', $budget->branch_id))
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as ym, sum(amount) as total")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn($r) => ['month' => $r->ym, 'total' => round((float) $r->total, 2)]);

        // Days elapsed vs days remaining ratio (linear pace check)
        $daysTotal     = max(1, $start->diffInDays($end) + 1);
        $daysElapsed   = max(0, min($daysTotal, $start->diffInDays(Carbon::now())));
        $expectedPercent = round(($daysElapsed / $daysTotal) * 100, 1);

        return [
            'budget' => [
                'id'           => $budget->id,
                'title'        => $budget->title,
                'fiscal_year'  => $budget->fiscal_year,
                'branch_id'    => $budget->branch_id,
                'branch_name'  => $budget->branch?->name,
                'start_date'   => $budget->start_date->format('Y-m-d'),
                'end_date'     => $budget->end_date->format('Y-m-d'),
                'warning_threshold_percent' => $budget->warning_threshold_percent,
                'status'       => $budget->status,
            ],
            'totals' => [
                'total_budget'    => (float) $budget->total_budget,
                'total_allocated' => round($totalAllocated, 2),
                'total_actual'    => round($totalActual, 2),
                'total_remaining' => round($totalRemaining, 2),
                'percent_used'    => $totalPercent,
                'expected_percent'=> $expectedPercent,
                'health'          => $this->health($totalPercent, $budget->warning_threshold_percent),
                'overspent'       => $totalActual > $totalAllocated,
                'pace'            => $this->pace($totalPercent, $expectedPercent),
            ],
            'categories' => $categories->values(),
            'monthly_burn' => $burn,
        ];
    }

    private function health(float $percent, int $warning): string
    {
        if ($percent >= 90) return 'critical';
        if ($percent >= $warning) return 'warning';
        if ($percent >= 50) return 'normal';
        return 'healthy';
    }

    private function pace(float $usedPercent, float $expectedPercent): string
    {
        $delta = $usedPercent - $expectedPercent;
        if ($delta >= 15)  return 'ahead';
        if ($delta <= -15) return 'behind';
        return 'on_track';
    }
}
