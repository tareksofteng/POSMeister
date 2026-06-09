<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | DashboardInsightsService — Phase AC Round 1
 |--------------------------------------------------------------------------
 |
 | Rule-based "insights" that the executive dashboard surfaces in plain
 | English. No AI, no LLM — every insight is a deterministic SQL
 | aggregate that crosses a threshold.
 |
 | Each insight is shaped as:
 |
 |     [
 |        'kind'     => 'sales|customer|inventory|cash|receivables|...',
 |        'severity' => 'positive|info|warning|danger',
 |        'title'    => 'Sales 15% lower than previous week.',
 |        'detail'   => 'Optional supporting numbers.',
 |        'action'   => ['label' => 'menu.sales', 'route' => 'sales'],   // optional
 |        'meta'     => [...],
 |     ]
 |
 | The frontend dashboard maps `kind` → an icon and `severity` → a tone.
 | If a rule doesn't trip, it simply contributes nothing — the response
 | is a sparse list (typically 3–6 insights at any given time).
 |
 | Workspace-aware via BranchContextService — single-branch admins see
 | only their own data; Main Branch sees the aggregate.
 */
class DashboardInsightsService
{
    public function compute(int $limit = 8): array
    {
        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $branchId   = $this->resolveBranchId();

        $insights = [];

        // Each detector returns either null (no insight) or an insight
        // array. Wrap in safe() so a single broken aggregation doesn't
        // sink the whole dashboard.
        $insights[] = $this->safe(fn () => $this->salesVsLastWeek($branchId));
        $insights[] = $this->safe(fn () => $this->customerConcentration($branchId, $monthStart));
        $insights[] = $this->safe(fn () => $this->productsNearStockout($branchId));
        $insights[] = $this->safe(fn () => $this->receivablesTrend($branchId));
        $insights[] = $this->safe(fn () => $this->cashHealth($branchId));
        $insights[] = $this->safe(fn () => $this->topProductShare($branchId, $monthStart));
        $insights[] = $this->safe(fn () => $this->newCustomerInflow($branchId));
        $insights[] = $this->safe(fn () => $this->idleCashbookSignal($branchId));

        return collect($insights)
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    // ── Rules ────────────────────────────────────────────────────────────

    /**
     * Compares last 7 days revenue against the prior 7 days. ≥ ±10% shift
     * flips the colour. Smaller shifts produce a neutral info card.
     */
    private function salesVsLastWeek(?int $branchId): ?array
    {
        if (!Schema::hasTable('sales')) return null;

        $current = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', now()->subDays(7)->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        $prior = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', now()->subDays(14)->toDateString())
            ->whereDate('sale_date', '<',  now()->subDays(7)->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        if ($prior < 100) return null;     // not enough baseline to compare
        $delta = (($current - $prior) / $prior) * 100;
        $abs = abs(round($delta, 1));

        if ($abs < 5) {
            return [
                'kind'     => 'sales',
                'severity' => 'info',
                'title'    => "Sales steady — within 5% of last week.",
                'detail'   => "This week: " . number_format($current, 0) . " · last week: " . number_format($prior, 0),
                'meta'     => ['delta' => round($delta, 1)],
            ];
        }

        return [
            'kind'     => 'sales',
            'severity' => $delta >= 0 ? 'positive' : 'warning',
            'title'    => $delta >= 0
                ? "Sales {$abs}% higher than previous week."
                : "Sales are {$abs}% lower than previous week.",
            'detail'   => "This week: " . number_format($current, 0) . " · last week: " . number_format($prior, 0),
            'action'   => ['label' => 'menu.salesRecord', 'route' => 'sales-record'],
            'meta'     => ['delta' => round($delta, 1)],
        ];
    }

    /**
     * Single customer contributing > 25% of monthly revenue → concentration risk.
     */
    private function customerConcentration(?int $branchId, string $monthStart): ?array
    {
        if (!Schema::hasTable('sales')) return null;

        $rows = DB::table('sales')
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('customer_id, SUM(grand_total) as total')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) return null;
        $total = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');
        if ($total <= 0) return null;

        $top = $rows->first();
        $share = ((float) $top->total) / $total * 100;
        if ($share < 25) return null;

        $name = Schema::hasTable('customers')
            ? (DB::table('customers')->where('id', $top->customer_id)->value('name') ?? "#{$top->customer_id}")
            : "#{$top->customer_id}";

        return [
            'kind'     => 'customer',
            'severity' => $share >= 40 ? 'warning' : 'info',
            'title'    => "{$name} contributes " . round($share, 1) . "% of revenue.",
            'detail'   => "Diversify or strengthen this relationship — single-customer concentration risk.",
            'action'   => ['label' => 'menu.customers', 'route' => 'customers'],
            'meta'     => ['customer_id' => (int) $top->customer_id, 'share_pct' => round($share, 1)],
        ];
    }

    /**
     * Products at or below reorder level. Singular vs plural copy.
     */
    private function productsNearStockout(?int $branchId): ?array
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('products')) return null;

        $count = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('p.reorder_level', '>', 0)
            ->whereRaw('i.quantity <= p.reorder_level')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->count();

        if ($count === 0) return null;

        return [
            'kind'     => 'inventory',
            'severity' => $count > 10 ? 'danger' : 'warning',
            'title'    => $count === 1
                ? "1 product is near stockout."
                : "{$count} products are near stockout.",
            'detail'   => "Create a purchase order before they sell out.",
            'action'   => ['label' => 'menu.inventory', 'route' => 'inventory'],
            'meta'     => ['count' => $count],
        ];
    }

    /**
     * Compare receivables vs 30 days ago. + 10% triggers a warning.
     */
    private function receivablesTrend(?int $branchId): ?array
    {
        if (!Schema::hasTable('sales')) return null;

        $now = (float) DB::table('sales')
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('due_amount');

        // Best-effort baseline: sales created > 30d ago that still have
        // a balance today. Approximates "the previous month's outstanding".
        $baseline = (float) DB::table('sales')
            ->where('status', 'active')
            ->where('sale_date', '<', now()->subDays(30)->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('due_amount');

        if ($baseline < 1000) {
            if ($now > 1000) {
                return [
                    'kind'     => 'receivables',
                    'severity' => 'info',
                    'title'    => "Outstanding receivables: " . number_format($now, 0) . ".",
                    'detail'   => "Track payments to maintain healthy cash flow.",
                    'action'   => ['label' => 'menu.customerDue', 'route' => 'customer-due'],
                ];
            }
            return null;
        }

        $delta = (($now - $baseline) / $baseline) * 100;
        if ($delta < 10) return null;

        return [
            'kind'     => 'receivables',
            'severity' => $delta > 30 ? 'danger' : 'warning',
            'title'    => "Outstanding receivables increased by " . round($delta, 1) . "%.",
            'detail'   => "Current: " . number_format($now, 0) . " · 30 days ago: " . number_format($baseline, 0),
            'action'   => ['label' => 'menu.customerDue', 'route' => 'customer-due'],
            'meta'     => ['delta_pct' => round($delta, 1)],
        ];
    }

    /**
     * Cash health — positive insight when liquidity is healthy.
     */
    private function cashHealth(?int $branchId): ?array
    {
        $cash = $this->accountingBalance('1000', $branchId);
        $bank = $this->accountingBalance('1100', $branchId);
        $liquid = $cash + $bank;

        if ($liquid <= 0) {
            return [
                'kind'     => 'cash',
                'severity' => 'danger',
                'title'    => "Cash position is critical.",
                'detail'   => "Combined cash + bank balance is " . number_format($liquid, 0) . ". Restore liquidity immediately.",
                'action'   => ['label' => 'menu.cashbook', 'route' => 'cashbooks'],
            ];
        }
        if ($liquid > 100000) {
            return [
                'kind'     => 'cash',
                'severity' => 'positive',
                'title'    => "Cash balance healthy.",
                'detail'   => "Combined cash + bank: " . number_format($liquid, 0),
            ];
        }
        return null;
    }

    /**
     * One product carries > 30% of the month's units sold.
     */
    private function topProductShare(?int $branchId, string $monthStart): ?array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) return null;

        $totalUnits = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->sum('si.quantity');

        if ($totalUnits <= 0) return null;

        $top = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('p.id, p.name, SUM(si.quantity) as units')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('units')
            ->first();

        if (!$top || !$top->units) return null;

        $share = ((float) $top->units) / $totalUnits * 100;
        if ($share < 30) return null;

        return [
            'kind'     => 'inventory',
            'severity' => 'info',
            'title'    => "{$top->name} drives " . round($share, 1) . "% of unit sales.",
            'detail'   => "Ensure stock cover for your best-selling product.",
            'action'   => ['label' => 'menu.products', 'route' => 'products'],
            'meta'     => ['product_id' => (int) $top->id, 'share_pct' => round($share, 1)],
        ];
    }

    /**
     * New customers added this month vs last. Positive when up.
     */
    private function newCustomerInflow(?int $branchId): ?array
    {
        if (!Schema::hasTable('customers')) return null;

        $thisMonth = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', now()->startOfMonth()->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $lastMonth = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', now()->subMonth()->startOfMonth()->toDateString())
            ->whereDate('created_at', '<',  now()->startOfMonth()->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        if ($thisMonth < 3 && $lastMonth < 3) return null;
        if ($thisMonth >= $lastMonth) {
            return [
                'kind'     => 'customer',
                'severity' => 'positive',
                'title'    => "{$thisMonth} new customer(s) this month.",
                'detail'   => "Last month: {$lastMonth}.",
            ];
        }
        // dip
        $delta = $lastMonth - $thisMonth;
        if ($delta < 2) return null;
        return [
            'kind'     => 'customer',
            'severity' => 'warning',
            'title'    => "Customer inflow down by {$delta}.",
            'detail'   => "This month: {$thisMonth} · last month: {$lastMonth}",
        ];
    }

    /**
     * Cashbook that hasn't seen a transaction in 14+ days.
     */
    private function idleCashbookSignal(?int $branchId): ?array
    {
        if (!Schema::hasTable('cashbooks') || !Schema::hasTable('journal_entry_lines')) return null;

        $cutoff = now()->subDays(14)->toDateString();
        $idle = DB::table('cashbooks as c')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'c.coa_account_id')
            ->leftJoin('journal_entry_lines as jl', 'jl.account_id', '=', 'a.id')
            ->where('c.is_active', true)
            ->when($branchId, fn($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.id, c.name, MAX(jl.created_at) as last_tx')
            ->groupBy('c.id', 'c.name')
            ->get()
            ->filter(fn ($r) => !$r->last_tx || $r->last_tx < $cutoff);

        if ($idle->isEmpty()) return null;

        return [
            'kind'     => 'cash',
            'severity' => 'info',
            'title'    => $idle->count() === 1
                ? "1 cashbook hasn't been used in 14+ days."
                : $idle->count() . " cashbooks haven't been used in 14+ days.",
            'detail'   => "Verify the books are still relevant or archive unused ones.",
            'action'   => ['label' => 'menu.cashbook', 'route' => 'cashbooks'],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }

    private function accountingBalance(string $code, ?int $branchId): float
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('journal_entry_lines')) return 0.0;
        $account = DB::table('chart_of_accounts')->where('account_code', $code)->first();
        if (!$account) return 0.0;

        $row = DB::table('journal_entry_lines')
            ->where('account_id', $account->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $d = (float) $row->d;
        $c = (float) $row->c;
        return $account->normal_balance === 'debit' ? $d - $c : $c - $d;
    }

    /**
     * Each rule wrapped so a renamed column on one table doesn't sink
     * the whole insights endpoint. Failures are logged but never raised.
     */
    private function safe(callable $fn): ?array
    {
        try   { return $fn(); }
        catch (\Throwable $e) { report($e); return null; }
    }
}
