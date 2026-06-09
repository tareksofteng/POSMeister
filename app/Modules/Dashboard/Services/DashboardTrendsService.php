<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | DashboardTrendsService — Phase AC Round 2
 |--------------------------------------------------------------------------
 |
 | Time-series builder for the executive dashboard's trend chart. Each
 | call returns a `[{ date: 'YYYY-MM-DD', value: N }]` series zero-filled
 | for the requested window so the frontend chart renders a contiguous
 | line even on quiet days.
 |
 | Four metrics supported:
 |
 |   revenue   — SUM(sales.grand_total) per day, status=active
 |   profit    — revenue minus COGS (sale_items.cost_price * quantity)
 |   purchase  — SUM(purchases.total_amount) per day
 |   cash_flow — daily cashbook delta: payments in − payments out
 |
 | Three windows: 7d / 30d / 90d. All scope through BranchContextService
 | so switching the Topbar workspace re-scopes every metric.
 |
 | Each query is single-table or single-join, indexed by sale_date /
 | purchase_date / payment_date — so even on 90 days the call stays fast.
 */
class DashboardTrendsService
{
    public const METRICS = ['revenue', 'profit', 'purchase', 'cash_flow'];
    public const WINDOWS = [7, 30, 90];

    public function series(string $metric, int $days): array
    {
        if (!in_array($metric, self::METRICS, true))     $metric = 'revenue';
        if (!in_array($days,   self::WINDOWS,  true))    $days   = 30;

        $branchId = $this->resolveBranchId();
        $from = Carbon::today()->subDays($days - 1);

        $points = match ($metric) {
            'revenue'   => $this->revenue($branchId, $from),
            'profit'    => $this->profit($branchId, $from),
            'purchase'  => $this->purchase($branchId, $from),
            'cash_flow' => $this->cashFlow($branchId, $from),
        };

        return $this->zeroFill($points, $from, $days);
    }

    // ── Metrics ──────────────────────────────────────────────────────────

    private function revenue(?int $branchId, Carbon $from): array
    {
        if (!Schema::hasTable('sales')) return [];
        return DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(sale_date) as day, COALESCE(SUM(grand_total), 0) as value')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('value', 'day')
            ->all();
    }

    private function profit(?int $branchId, Carbon $from): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasTable('sale_items')) return [];

        // Daily revenue
        $revenue = $this->revenue($branchId, $from);
        if (!$revenue) return [];

        // Daily COGS — sum of (qty * cost_price) joined to sale_date.
        $cogs = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('DATE(s.sale_date) as day, COALESCE(SUM(si.quantity * si.cost_price), 0) as value')
            ->groupBy('day')
            ->pluck('value', 'day')
            ->all();

        // Per-day delta — defends against negative profit (loss days) by
        // letting the value go below zero.
        $out = [];
        foreach ($revenue as $day => $rev) {
            $out[$day] = (float) $rev - (float) ($cogs[$day] ?? 0);
        }
        return $out;
    }

    private function purchase(?int $branchId, Carbon $from): array
    {
        if (!Schema::hasTable('purchases')) return [];
        return DB::table('purchases')
            ->whereDate('purchase_date', '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(purchase_date) as day, COALESCE(SUM(total_amount), 0) as value')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('value', 'day')
            ->all();
    }

    private function cashFlow(?int $branchId, Carbon $from): array
    {
        // Inflow proxy: customer_payments + supplier refunds. Outflow
        // proxy: supplier_payments + expenses + payroll. Net per day.
        $inflow  = $this->dailySum($branchId, $from, 'customer_payments', 'amount', 'payment_date');
        $outflow1 = $this->dailySum($branchId, $from, 'supplier_payments', 'amount', 'payment_date');
        $outflow2 = $this->dailySum($branchId, $from, 'expenses',          'amount', 'expense_date');
        $outflow3 = $this->dailySum($branchId, $from, 'payslips',          'net_salary', 'payment_date');

        $days = array_unique(array_merge(
            array_keys($inflow),
            array_keys($outflow1),
            array_keys($outflow2),
            array_keys($outflow3),
        ));
        $out = [];
        foreach ($days as $day) {
            $out[$day] = (float) ($inflow[$day]   ?? 0)
                       - (float) ($outflow1[$day] ?? 0)
                       - (float) ($outflow2[$day] ?? 0)
                       - (float) ($outflow3[$day] ?? 0);
        }
        ksort($out);
        return $out;
    }

    private function dailySum(?int $branchId, Carbon $from, string $table, string $column, string $dateColumn): array
    {
        if (!Schema::hasTable($table)) return [];

        $q = DB::table($table)
            ->whereDate($dateColumn, '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        // Some tables carry a status column the dashboard would want to
        // filter on — apply it only when present.
        if ($table === 'expenses') {
            $q->whereIn('status', ['approved', 'paid']);
        }
        if ($table === 'payslips') {
            $q->where('status', 'paid');
        }

        return $q->selectRaw("DATE({$dateColumn}) as day, COALESCE(SUM({$column}), 0) as value")
                 ->groupBy('day')
                 ->orderBy('day')
                 ->pluck('value', 'day')
                 ->all();
    }

    // ── Zero-fill the series so the frontend chart renders contiguous ────

    private function zeroFill(array $points, Carbon $from, int $days): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = [
                'date'  => $d,
                'value' => round((float) ($points[$d] ?? 0), 2),
            ];
        }
        return $out;
    }

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
