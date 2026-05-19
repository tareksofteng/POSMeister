<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ExpenseReportService
{
    public function dashboard(?int $branchId = null): array
    {
        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd   = $today->copy()->endOfMonth();
        $prevStart  = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd    = $monthStart->copy()->subDay();

        $base = Expense::query();
        $this->applyScope($base, $branchId);

        $total = (clone $base)->sum('amount');
        $countTotal = (clone $base)->count();

        $byStatus = (clone $base)->selectRaw('status, count(*) as c, coalesce(sum(amount), 0) as total')
            ->groupBy('status')->get();

        $thisMonth = (clone $base)->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');
        $lastMonth = (clone $base)->whereBetween('expense_date', [$prevStart,  $prevEnd])->sum('amount');

        $topCategories = (clone $base)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.id, expense_categories.name, count(*) as count, sum(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byPaymentMethod = (clone $base)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
            ->groupBy('payment_method')
            ->get();

        $paidTotal     = (clone $base)->where('status', 'paid')->sum('amount');
        $unpaidTotal   = (clone $base)->whereIn('status', ['pending', 'approved'])->sum('amount');

        return [
            'total_amount'      => round((float) $total, 2),
            'total_count'       => (int) $countTotal,
            'paid_total'        => round((float) $paidTotal, 2),
            'unpaid_total'      => round((float) $unpaidTotal, 2),
            'this_month_total'  => round((float) $thisMonth, 2),
            'last_month_total'  => round((float) $lastMonth, 2),
            'mom_delta'         => $lastMonth > 0
                                    ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                                    : null,
            'by_status'         => $byStatus->map(fn($r) => [
                'status' => $r->status,
                'count'  => (int) $r->c,
                'total'  => round((float) $r->total, 2),
            ]),
            'top_categories'    => $topCategories->map(fn($r) => [
                'id'     => $r->id,
                'name'   => $r->name,
                'count'  => (int) $r->count,
                'total'  => round((float) $r->total, 2),
            ]),
            'by_payment_method' => $byPaymentMethod->map(fn($r) => [
                'method' => $r->payment_method,
                'count'  => (int) $r->count,
                'total'  => round((float) $r->total, 2),
            ]),
        ];
    }

    public function categoryBreakdown(array $filters): array
    {
        $q = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        $this->applyScope($q, $filters['branch_id'] ?? null);
        $this->applyDateRange($q, $filters);

        $rows = $q->selectRaw('expense_categories.id, expense_categories.name, expense_categories.code,
                               count(*) as count, sum(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code')
            ->orderByDesc('total')
            ->get();

        $grand = (float) $rows->sum('total');

        return [
            'period' => [
                'from' => $filters['from'] ?? null,
                'to'   => $filters['to']   ?? null,
            ],
            'grand_total' => round($grand, 2),
            'rows' => $rows->map(fn($r) => [
                'id'    => $r->id,
                'name'  => $r->name,
                'code'  => $r->code,
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
                'share' => $grand > 0 ? round((float) $r->total / $grand * 100, 1) : 0,
            ]),
        ];
    }

    public function monthlyTrend(int $year, ?int $branchId = null): array
    {
        $q = Expense::query();
        $this->applyScope($q, $branchId);

        $rows = $q->selectRaw('MONTH(expense_date) as month, sum(amount) as total, count(*) as count')
            ->whereYear('expense_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $rows->get($m);
            $months[] = [
                'month' => $m,
                'count' => (int) ($row->count ?? 0),
                'total' => round((float) ($row->total ?? 0), 2),
            ];
        }

        return [
            'year'   => $year,
            'months' => $months,
            'total'  => round((float) array_sum(array_column($months, 'total')), 2),
        ];
    }

    public function branchBreakdown(array $filters): array
    {
        if (!$this->isAdmin()) {
            return ['rows' => [], 'grand_total' => 0];
        }

        $q = Expense::query()
            ->join('branches', 'branches.id', '=', 'expenses.branch_id');
        $this->applyDateRange($q, $filters);

        $rows = $q->selectRaw('branches.id, branches.name,
                               count(*) as count,
                               sum(expenses.amount) as total,
                               sum(case when expenses.status = \'paid\' then expenses.amount else 0 end) as paid,
                               sum(case when expenses.status = \'pending\' then expenses.amount else 0 end) as pending')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total')
            ->get();

        return [
            'period' => [
                'from' => $filters['from'] ?? null,
                'to'   => $filters['to']   ?? null,
            ],
            'grand_total' => round((float) $rows->sum('total'), 2),
            'rows' => $rows->map(fn($r) => [
                'id'      => $r->id,
                'name'    => $r->name,
                'count'   => (int) $r->count,
                'total'   => round((float) $r->total, 2),
                'paid'    => round((float) $r->paid, 2),
                'pending' => round((float) $r->pending, 2),
            ]),
        ];
    }

    private function applyScope($query, ?int $branchId = null): void
    {
        $user = Auth::user();
        if ($this->isAdmin() && $branchId) {
            $query->where('expenses.branch_id', $branchId);
            return;
        }
        if (!$this->isAdmin() && $user?->branch_id) {
            $query->where('expenses.branch_id', $user->branch_id);
        }
    }

    private function applyDateRange($query, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->whereDate('expenses.expense_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('expenses.expense_date', '<=', $filters['to']);
        }
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
