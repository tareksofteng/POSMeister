<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Services\BranchContextService;
use App\Modules\Dashboard\Services\BusinessHealthService;
use App\Modules\Dashboard\Services\DashboardInsightsService;
use App\Modules\Dashboard\Services\DashboardTrendsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Executive home-dashboard endpoint. Reads across every module that ships
 * in POSmeister (sales, inventory, accounting, CRM, OMS, HRM) and returns
 * one JSON payload the SPA can render without further round-trips.
 *
 * All queries are branch-scoped: admin sees the whole company, everyone
 * else sees only their own branch. Pure read; no side effects.
 */
class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/trends?metric=revenue|profit|purchase|cash_flow&days=7|30|90
     *
     * Phase AC Round 2 — Executive trend chart data source. Kept out of
     * the main stats() payload so a 90-day view isn't computed for users
     * who never expand the period switcher. Workspace-aware via the
     * service.
     */
    public function trends(Request $request, DashboardTrendsService $trends): JsonResponse
    {
        $metric = $request->input('metric', 'revenue');
        $days   = (int) $request->input('days', 30);
        return response()->json(['data' => [
            'metric' => $metric,
            'days'   => $days,
            'series' => $trends->series($metric, $days),
        ]]);
    }

    public function stats(): JsonResponse
    {
        // Topbar workspace context is binding. NULL = Main Branch / All
        // Branches super-workspace (admin) — no branch filter applied so
        // every `when($branchId, ...)` below stays inert and the KPIs
        // aggregate cross-branch. Specific branch id scopes every block.
        $user = auth()->user();
        $ctx  = app(BranchContextService::class);
        $branchId = $ctx->isMainBranch() ? null : $ctx->current();

        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $weekAgo    = $today->copy()->subDays(13)->toDateString();
        $yesterday  = $today->copy()->subDay()->toDateString();

        // Defensive block-level isolation — the dashboard aggregates a
        // dozen different subsystems. If ONE block throws (a renamed
        // column, a missing table after a migration rollback, a future
        // branch-scoping bug like the customers/sales ambiguity), the
        // whole executive view used to 500. Now each block degrades to
        // its empty-state default and the rest of the page renders, with
        // the failure surfaced in laravel.log for diagnosis.
        $safe = function (string $label, \Closure $fn, $fallback) {
            try { return $fn(); }
            catch (\Throwable $e) {
                logger()->warning("dashboard.block_failed.{$label}", [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile().':'.$e->getLine(),
                ]);
                return $fallback;
            }
        };

        return response()->json([
            'as_of'          => $today->toIso8601String(),
            'sales'          => $safe('sales',         fn() => $this->salesBlock($branchId, $today, $monthStart, $yesterday),
                                      ['today_revenue'=>0,'today_sales_count'=>0,'yesterday_revenue'=>0,'delta_vs_yesterday'=>null,'month_revenue'=>0,'month_collected'=>0,'month_outstanding'=>0,'month_sales_count'=>0]),
            'purchases'      => $safe('purchases',     fn() => $this->purchasesBlock($branchId, $today, $monthStart),
                                      ['today'=>0,'month'=>0,'supplier_paid_month'=>0,'customer_paid_month'=>0]),
            'finance'        => $safe('finance',       fn() => $this->financeBlock($branchId, $monthStart),
                                      ['revenue_month'=>0,'cogs_month'=>0,'expenses_month'=>0,'payroll_month'=>0,'net_profit_month'=>0,'gross_margin_pct'=>0,'cash_balance'=>0,'bank_balance'=>0]),
            'inventory'      => $safe('inventory',     fn() => $this->inventoryBlock($branchId),
                                      ['stock_value'=>0,'low_stock_count'=>0,'out_of_stock_count'=>0,'dead_stock_count'=>0]),
            'customers'      => $safe('customers',     fn() => $this->customersBlock($branchId),
                                      ['active_count'=>0,'new_this_month'=>0,'outstanding_due'=>0,'loyalty_points'=>0,'loyalty_liability'=>0]),
            'orders'         => $safe('orders',        fn() => $this->ordersBlock($branchId),
                                      ['open'=>0,'today'=>0,'delivered_month'=>0]),
            'hrm'            => $safe('hrm',           fn() => $this->hrmBlock($branchId),
                                      ['active_employees'=>0,'pending_approvals'=>0,'late_today'=>0]),
            'recent_sales'   => $safe('recent_sales',  fn() => $this->recentSales($branchId, 5),       []),
            'sales_trend'    => $safe('sales_trend',   fn() => $this->salesTrend($branchId, $weekAgo), []),
            'top_products'   => $safe('top_products',  fn() => $this->topProductsMonth($branchId, $monthStart, 5),  []),
            'top_customers'  => $safe('top_customers', fn() => $this->topCustomersMonth($branchId, $monthStart, 5), []),
            'alerts'         => $safe('alerts',        fn() => $this->alerts($branchId),        []),
            'activity'       => $safe('activity',      fn() => $this->recentActivity($branchId, 10), []),

            // Phase AC — Executive Dashboard 2.0 additions. Each lives in
            // its own service so the dashboard controller stays focused
            // on aggregation, and failures degrade independently via the
            // existing $safe() wrapper.
            'health'         => $safe('health',        fn() => app(BusinessHealthService::class)->compute(),
                                      ['score'=>0,'tier'=>'rose','delta'=>null,'subscores'=>[]]),
            'insights'       => $safe('insights',      fn() => app(DashboardInsightsService::class)->compute(), []),
            'branch_leaderboard' => $safe('branch_leaderboard', fn() => $this->branchLeaderboard($branchId, $today, $monthStart), []),
        ]);
    }

    /**
     * Phase: Executive dashboard upgrade — purchase totals today + MTD,
     * plus a small payments rollup. Mirrors salesBlock so the marquee
     * has matching shapes for both flows.
     */
    private function purchasesBlock(?int $branchId, Carbon $today, string $monthStart): array
    {
        if (!$this->tableExists('purchases')) {
            return ['today' => 0, 'month' => 0, 'supplier_paid_month' => 0, 'customer_paid_month' => 0];
        }

        $todayAmt = (float) DB::table('purchases')
            ->whereDate('purchase_date', $today)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('total_amount');

        $monthAmt = (float) DB::table('purchases')
            ->whereDate('purchase_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('total_amount');

        $supplierPaid = 0.0;
        $customerPaid = 0.0;
        if ($this->tableExists('supplier_payments')) {
            $supplierPaid = (float) DB::table('supplier_payments')
                ->whereDate('payment_date', '>=', $monthStart)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('amount');
        }
        if ($this->tableExists('customer_payments')) {
            $customerPaid = (float) DB::table('customer_payments')
                ->whereDate('payment_date', '>=', $monthStart)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('amount');
        }

        return [
            'today'               => round($todayAmt, 2),
            'month'               => round($monthAmt, 2),
            'supplier_paid_month' => round($supplierPaid, 2),
            'customer_paid_month' => round($customerPaid, 2),
        ];
    }

    // ---- KPI blocks ---------------------------------------------------------

    private function salesBlock(?int $branchId, Carbon $today, string $monthStart, string $yesterday): array
    {
        $todayRow = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', $today)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as cnt')
            ->first();

        $yesterdayRow = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', $yesterday)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as cnt')
            ->first();

        $monthRow = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('
                COALESCE(SUM(grand_total), 0) as revenue,
                COALESCE(SUM(cash_paid + card_paid), 0) as collected,
                COALESCE(SUM(due_amount), 0) as outstanding,
                COUNT(*) as cnt
            ')
            ->first();

        $todayRev     = (float) $todayRow->revenue;
        $yesterdayRev = (float) $yesterdayRow->revenue;
        $delta = $yesterdayRev > 0 ? round((($todayRev - $yesterdayRev) / $yesterdayRev) * 100, 1) : null;

        return [
            'today_revenue'       => $todayRev,
            'today_sales_count'   => (int) $todayRow->cnt,
            'yesterday_revenue'   => $yesterdayRev,
            'delta_vs_yesterday'  => $delta,
            'month_revenue'       => (float) $monthRow->revenue,
            'month_collected'     => (float) $monthRow->collected,
            'month_outstanding'   => (float) $monthRow->outstanding,
            'month_sales_count'   => (int) $monthRow->cnt,
        ];
    }

    /**
     * Net profit (rough): revenue − COGS − operating expenses − payroll.
     * Cash + bank balances come from the accounting journal when the
     * Accounting module is migrated; otherwise they default to 0.
     */
    private function financeBlock(?int $branchId, string $monthStart): array
    {
        $revenue = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        $cogs = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->sum(DB::raw('si.quantity * si.cost_price'));

        $expenses = (float) DB::table('expenses')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('expense_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('amount');

        $payroll = (float) DB::table('payslips')
            ->where('status', 'paid')
            ->whereDate('payment_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('net_salary');

        $netProfit = $revenue - $cogs - $expenses - $payroll;

        return [
            'revenue_month'    => round($revenue, 2),
            'cogs_month'       => round($cogs, 2),
            'expenses_month'   => round($expenses, 2),
            'payroll_month'    => round($payroll, 2),
            'net_profit_month' => round($netProfit, 2),
            'gross_margin_pct' => $revenue > 0 ? round((($revenue - $cogs) / $revenue) * 100, 1) : 0,
            'cash_balance'     => $this->accountingBalance('1000', $branchId),
            'bank_balance'     => $this->accountingBalance('1100', $branchId),
        ];
    }

    private function inventoryBlock(?int $branchId): array
    {
        $stockValue = (float) DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->sum(DB::raw('i.quantity * p.cost_price'));

        $lowStock = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('p.reorder_level', '>', 0)
            ->whereRaw('i.quantity <= p.reorder_level')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->count();

        $outOfStock = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('i.quantity', '<=', 0)
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->count();

        // Dead stock: on-hand AND no active sale in 90 days.
        $cutoff = Carbon::today()->subDays(90)->toDateString();
        $deadStock = DB::table('products as p')
            ->join('inventory as i', 'i.product_id', '=', 'p.id')
            ->leftJoinSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', 'active')
                    ->selectRaw('si.product_id, MAX(s.sale_date) as last_sale')
                    ->groupBy('si.product_id'),
                'l', 'l.product_id', '=', 'p.id'
            )
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('i.quantity', '>', 0)
            ->where(fn($q) => $q->where('l.last_sale', '<', $cutoff)->orWhereNull('l.last_sale'))
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->count(DB::raw('DISTINCT p.id'));

        return [
            'stock_value'         => round($stockValue, 2),
            'low_stock_count'     => $lowStock,
            'out_of_stock_count'  => $outOfStock,
            'dead_stock_count'    => $deadStock,
        ];
    }

    private function customersBlock(?int $branchId): array
    {
        $active = DB::table('customers')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $newThisMonth = DB::table('customers')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', Carbon::today()->startOfMonth())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $outstanding = (float) DB::table('sales')
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('due_amount');

        // Loyalty liability — sum of current points / redeem ratio (CRM module).
        $points = 0.0;
        $loyaltyLiability = 0.0;
        if ($this->tableExists('customer_loyalty_profiles')) {
            $points = (float) DB::table('customer_loyalty_profiles as p')
                ->join('customers as c', 'c.id', '=', 'p.customer_id')
                ->when($branchId, fn($q) => $q->where('c.branch_id', $branchId))
                ->whereNull('c.deleted_at')
                ->sum('p.current_points');

            $redeemRatio = $this->tableExists('loyalty_settings')
                ? (int) (DB::table('loyalty_settings')->where('id', 1)->value('redeem_points_per_currency') ?? 100)
                : 100;
            $loyaltyLiability = $redeemRatio > 0 ? round($points / $redeemRatio, 2) : 0;
        }

        return [
            'active_count'      => $active,
            'new_this_month'    => $newThisMonth,
            'outstanding_due'   => round($outstanding, 2),
            'loyalty_points'    => round($points, 2),
            'loyalty_liability' => $loyaltyLiability,
        ];
    }

    private function ordersBlock(?int $branchId): array
    {
        if (!$this->tableExists('orders')) {
            return ['open' => 0, 'today' => 0, 'delivered_month' => 0];
        }

        $open = DB::table('orders')
            ->whereIn('status', ['pending', 'confirmed', 'packed', 'shipped'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $today = DB::table('orders')
            ->whereDate('placed_at', Carbon::today())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $delivered = DB::table('orders')
            ->where('status', 'delivered')
            ->whereDate('delivered_at', '>=', Carbon::today()->startOfMonth())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        return ['open' => $open, 'today' => $today, 'delivered_month' => $delivered];
    }

    private function hrmBlock(?int $branchId): array
    {
        $activeEmployees = DB::table('employees')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $pendingApprovals = 0;
        if ($this->columnExists('payslips', 'approval_status')) {
            $pendingApprovals = DB::table('payslips')
                ->where('approval_status', 'submitted')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
        }

        $lateToday = 0;
        if ($this->tableExists('attendance')) {
            $lateToday = DB::table('attendance')
                ->whereDate('attendance_date', Carbon::today())
                ->where('is_late', true)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
        }

        return [
            'active_employees'  => $activeEmployees,
            'pending_approvals' => $pendingApprovals,
            'late_today'        => $lateToday,
        ];
    }

    // ---- Lists --------------------------------------------------------------

    private function recentSales(?int $branchId, int $limit): array
    {
        return DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.status', 'active')
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->orderByDesc('s.id')
            ->limit($limit)
            ->get([
                's.id', 's.sale_number', 's.sale_date',
                's.customer_name as fallback_name', 'c.name as customer_name',
                's.grand_total', 's.due_amount',
            ])
            ->map(fn($s) => [
                'id'            => (int) $s->id,
                'sale_number'   => $s->sale_number,
                'sale_date'     => $s->sale_date,
                'customer_name' => $s->customer_name ?? $s->fallback_name ?? 'Laufkunde',
                'grand_total'   => (float) $s->grand_total,
                'due_amount'    => (float) $s->due_amount,
            ])->all();
    }

    /**
     * Last 14 days of sales for the mini-trend chart.
     */
    private function salesTrend(?int $branchId, string $from): array
    {
        $rows = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $from)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('sale_date, COALESCE(SUM(grand_total), 0) as revenue, COUNT(*) as cnt')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $byDate = $rows->keyBy(fn($r) => Carbon::parse($r->sale_date)->toDateString());
        $out = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->toDateString();
            $row = $byDate->get($d);
            $out[] = [
                'date'    => $d,
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'count'   => (int) ($row->cnt ?? 0),
            ];
        }
        return $out;
    }

    private function topProductsMonth(?int $branchId, string $monthStart, int $limit): array
    {
        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('
                p.id, p.name, p.sku,
                SUM(si.quantity) as qty_sold,
                SUM(si.line_total) as revenue
            ')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'product_id' => (int) $r->id,
                'name'       => $r->name,
                'sku'        => $r->sku,
                'qty_sold'   => round((float) $r->qty_sold, 2),
                'revenue'    => round((float) $r->revenue, 2),
            ])->all();
    }

    private function topCustomersMonth(?int $branchId, string $monthStart, int $limit): array
    {
        // ⚠️ The customers table ALSO has a branch_id column, so a bare
        // `where('branch_id', …)` after the join below throws
        // "Column 'branch_id' in where clause is ambiguous" → 500.
        // Qualify with `sales.` to remove the ambiguity.
        return DB::table('sales')
            ->whereNotNull('customer_id')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->join('customers as c', 'c.id', '=', 'sales.customer_id')
            ->selectRaw('
                c.id, c.name,
                COUNT(*) as visits,
                COALESCE(SUM(sales.grand_total), 0) as revenue
            ')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'customer_id' => (int) $r->id,
                'name'        => $r->name,
                'visits'      => (int) $r->visits,
                'revenue'     => round((float) $r->revenue, 2),
            ])->all();
    }

    private function alerts(?int $branchId): array
    {
        $alerts = [];

        $inv = $this->inventoryBlock($branchId);
        if ($inv['low_stock_count'] > 0) {
            $alerts[] = ['severity' => 'warning',  'kind' => 'low_stock',     'count' => $inv['low_stock_count']];
        }
        if ($inv['out_of_stock_count'] > 0) {
            $alerts[] = ['severity' => 'critical', 'kind' => 'out_of_stock',  'count' => $inv['out_of_stock_count']];
        }
        if ($inv['dead_stock_count'] > 0) {
            $alerts[] = ['severity' => 'warning',  'kind' => 'dead_stock',    'count' => $inv['dead_stock_count']];
        }

        $overdue = DB::table('sales')
            ->where('status', 'active')
            ->where('due_amount', '>', 0)
            ->whereDate('sale_date', '<=', Carbon::today()->subDays(30)->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        if ($overdue > 0) {
            $alerts[] = ['severity' => 'warning', 'kind' => 'overdue_payments', 'count' => $overdue];
        }

        if ($this->columnExists('payslips', 'approval_status')) {
            $pending = DB::table('payslips')
                ->where('approval_status', 'submitted')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
            if ($pending > 0) {
                $alerts[] = ['severity' => 'info', 'kind' => 'payroll_pending', 'count' => $pending];
            }
        }

        return $alerts;
    }

    /**
     * Merged activity feed: sales + journal entries.
     * Sorted by time desc, capped at $limit.
     */
    private function recentActivity(?int $branchId, int $limit): array
    {
        $items = [];

        DB::table('sales')
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'sale_number', 'sale_date', 'grand_total', 'created_at'])
            ->each(function ($s) use (&$items) {
                // Title is just the document number — the activity feed
                // categorises by "kind" so the frontend renders the
                // localised label (Sale / Verkauf / মূল্য / بيع).
                $items[] = [
                    'kind'   => 'sale',
                    'title'  => $s->sale_number,
                    'amount' => (float) $s->grand_total,
                    'at'     => (string) $s->created_at,
                ];
            });

        if ($this->tableExists('journal_entries')) {
            DB::table('journal_entries')
                ->where('status', 'posted')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['entry_number', 'narration', 'total_debit', 'created_at'])
                ->each(function ($e) use (&$items) {
                    $items[] = [
                        'kind'   => 'journal',
                        'title'  => $e->narration ?: $e->entry_number,
                        'amount' => (float) $e->total_debit,
                        'at'     => (string) $e->created_at,
                    ];
                });
        }

        usort($items, fn($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
        return array_slice($items, 0, $limit);
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * Returns the balance of a chart-of-accounts code from the journal,
     * 0 if the Accounting module isn't migrated yet.
     */
    private function accountingBalance(string $code, ?int $branchId): float
    {
        if (!$this->tableExists('chart_of_accounts') || !$this->tableExists('journal_entry_lines')) {
            return 0.0;
        }
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
     * Branch leaderboard — Phase AC. Only meaningful when the user is
     * looking at the aggregate (Main Branch / All Branches super
     * workspace). For a specific branch workspace we return an empty
     * array so the frontend can hide the section cleanly.
     *
     * Each row carries the branch's today + month sales + active
     * customer count + an "approx_profit" estimate (revenue minus a
     * conservative 25% COGS proxy when we don't have per-branch cost
     * data). Sorted by month sales descending so the top branch is
     * easy to spot.
     */
    private function branchLeaderboard(?int $branchId, Carbon $today, string $monthStart): array
    {
        // When in a single branch workspace, the leaderboard collapses
        // into a one-row trivial answer — hide it entirely.
        if ($branchId !== null) return [];
        if (!$this->tableExists('branches') || !$this->tableExists('sales')) return [];

        $branches = DB::table('branches')->where('is_active', true)->get(['id', 'name', 'code']);
        if ($branches->isEmpty()) return [];

        $todayMap = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', $today)
            ->selectRaw('branch_id, SUM(grand_total) as total, COUNT(*) as cnt')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $monthMap = DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->selectRaw('branch_id, SUM(grand_total) as total, COUNT(*) as cnt')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        // Customers may not have branch_id everywhere; we fall back to a
        // "customers seen via sales this month" approximation in that case.
        $customerMap = collect();
        if ($this->columnExists('customers', 'branch_id')) {
            $customerMap = DB::table('customers')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->selectRaw('branch_id, COUNT(*) as cnt')
                ->groupBy('branch_id')
                ->pluck('cnt', 'branch_id');
        } else {
            $customerMap = DB::table('sales')
                ->whereNotNull('customer_id')
                ->whereDate('sale_date', '>=', $monthStart)
                ->selectRaw('branch_id, COUNT(DISTINCT customer_id) as cnt')
                ->groupBy('branch_id')
                ->pluck('cnt', 'branch_id');
        }

        $rows = $branches->map(function ($b) use ($todayMap, $monthMap, $customerMap) {
            $today  = $todayMap[$b->id]  ?? null;
            $month  = $monthMap[$b->id]  ?? null;
            $monthRev = (float) ($month->total ?? 0);
            return [
                'branch_id'        => (int) $b->id,
                'name'             => $b->name,
                'code'             => $b->code ?? null,
                'today_sales'      => (float) ($today->total ?? 0),
                'today_count'      => (int)   ($today->cnt   ?? 0),
                'month_sales'      => $monthRev,
                'month_count'      => (int)   ($month->cnt   ?? 0),
                // Conservative profit proxy — pricing is set so 25% is a
                // realistic floor margin. Used purely for the leaderboard's
                // tiny bar; not used in any accounting figure.
                'approx_profit'    => round($monthRev * 0.25, 2),
                'active_customers' => (int) ($customerMap[$b->id] ?? 0),
            ];
        })
        ->sortByDesc('month_sales')
        ->values()
        ->all();

        return $rows;
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
