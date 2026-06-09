<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | BusinessHealthService — Phase AC Round 1
 |--------------------------------------------------------------------------
 |
 | Computes a single 0–100 score the business owner can glance at and
 | know whether today is going well. The score is composed of FIVE
 | independent sub-scores that each contribute up to a weighted maximum:
 |
 |     SALES TRAJECTORY   25 pts — today's revenue vs 7-day average
 |     PROFITABILITY      20 pts — gross margin proxy from sale + cost
 |     CASH POSITION      20 pts — cash + bank balance vs 30-day spend
 |     RECEIVABLES        15 pts — outstanding vs total billed
 |     OPERATIONAL RISK   20 pts — open critical alerts + low stock
 |
 | Each sub-score is normalised so the total sums to a 0–100 integer.
 | The breakdown is returned alongside the score so the frontend can
 | render a "why" tooltip without re-computing.
 |
 | Workspace-aware: routed through BranchContextService so switching
 | the Topbar workspace re-scopes every sub-score. NULL/Main Branch
 | sees the aggregate; a specific branch sees only its own data.
 |
 | All queries use the same Schema::hasTable / hasColumn guards as the
 | DashboardController so a missing table degrades to 0 instead of 500.
 */
class BusinessHealthService
{
    public function compute(): array
    {
        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $weekStart  = $today->copy()->subDays(7)->toDateString();

        $branchId = $this->resolveBranchId();

        $sales = $this->scoreSales($branchId, $today, $weekStart);
        $profit = $this->scoreProfit($branchId, $monthStart);
        $cash = $this->scoreCash($branchId, $monthStart);
        $receivables = $this->scoreReceivables($branchId);
        $risk = $this->scoreRisk($branchId);

        $total = $sales['score'] + $profit['score'] + $cash['score']
               + $receivables['score'] + $risk['score'];

        return [
            'score'    => max(0, min(100, (int) round($total))),
            'tier'     => $this->tierFor((int) round($total)),
            'delta'    => $sales['delta_pct'],   // headline delta for the hero
            'as_of'    => now()->toIso8601String(),
            'subscores' => [
                'sales'       => $sales,
                'profit'      => $profit,
                'cash'        => $cash,
                'receivables' => $receivables,
                'risk'        => $risk,
            ],
        ];
    }

    /**
     * Bucket the 0–100 score into one of four tiers. Drives the colour
     * ring on the BusinessHealthCard frontend.
     */
    private function tierFor(int $score): string
    {
        if ($score >= 90) return 'emerald';
        if ($score >= 70) return 'sky';
        if ($score >= 50) return 'amber';
        return 'rose';
    }

    // ── Sub-scores ───────────────────────────────────────────────────────

    /**
     * 25 pts max. Today's revenue compared to the 7-day average:
     *   - matches average    → 18 pts
     *   - +20% vs average    → 25 pts (cap)
     *   - -50% vs average    → 0 pts
     *   - quiet days (avg <1k) → flat 12.5 pts so a Sunday isn't punished
     */
    private function scoreSales(?int $branchId, Carbon $today, string $weekStart): array
    {
        if (!Schema::hasTable('sales')) {
            return ['score' => 12.5, 'label' => 'Sales trajectory', 'delta_pct' => null, 'note' => 'No sales data'];
        }

        $todayAmt = (float) DB::table('sales')
            ->whereDate('sale_date', $today)
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        $weekAmt = (float) DB::table('sales')
            ->whereDate('sale_date', '>=', $weekStart)
            ->whereDate('sale_date', '<', $today)
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        $avg = $weekAmt > 0 ? $weekAmt / 7 : 0;
        if ($avg < 1000) {
            return ['score' => 12.5, 'label' => 'Sales trajectory', 'delta_pct' => null, 'note' => 'Quiet baseline — score neutral.'];
        }

        $ratio = $todayAmt / $avg;
        // 0.5 → 0 pts, 1.0 → 18 pts, 1.2 → 25 pts
        $score = max(0, min(25, ($ratio - 0.5) * 36));
        $deltaPct = round(($ratio - 1) * 100, 1);

        return [
            'score'     => $score,
            'label'     => 'Sales trajectory',
            'delta_pct' => $deltaPct,
            'note'      => $deltaPct >= 0
                ? "Today {$deltaPct}% above 7-day average"
                : "Today " . abs($deltaPct) . "% below 7-day average",
        ];
    }

    /**
     * 20 pts max. Gross margin proxy from sale_items.cost_price.
     *   - margin >= 30%  → 20 pts
     *   - margin >= 15%  → 14 pts
     *   - margin >= 5%   → 8 pts
     *   - margin < 5%    → 4 pts
     *   - loss-making    → 0 pts
     */
    private function scoreProfit(?int $branchId, string $monthStart): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) {
            return ['score' => 10, 'label' => 'Profitability', 'margin_pct' => null, 'note' => 'No margin data'];
        }

        $revenue = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->sum('grand_total');

        if ($revenue <= 0) {
            return ['score' => 10, 'label' => 'Profitability', 'margin_pct' => null, 'note' => 'No revenue yet'];
        }

        $cogs = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->sum(DB::raw('si.quantity * si.cost_price'));

        $margin = ($revenue - $cogs) / $revenue * 100;

        $score = match (true) {
            $margin >= 30 => 20,
            $margin >= 15 => 14,
            $margin >= 5  => 8,
            $margin > 0   => 4,
            default       => 0,
        };

        return [
            'score'      => $score,
            'label'      => 'Profitability',
            'margin_pct' => round($margin, 1),
            'note'       => $margin > 0
                ? "Gross margin " . round($margin, 1) . "%"
                : "Operating at a loss — review pricing",
        ];
    }

    /**
     * 20 pts max. Cash + bank vs 30-day spend signal. We don't have a
     * burn-rate column so we approximate with last 30 days of expenses
     * + payroll. Score = ratio of (cash + bank) to monthly spend.
     */
    private function scoreCash(?int $branchId, string $monthStart): array
    {
        $cash = $this->accountingBalance('1000', $branchId);
        $bank = $this->accountingBalance('1100', $branchId);
        $liquid = $cash + $bank;

        $expenses = Schema::hasTable('expenses')
            ? (float) DB::table('expenses')
                ->whereIn('status', ['approved', 'paid'])
                ->whereDate('expense_date', '>=', $monthStart)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('amount')
            : 0;

        $payroll = Schema::hasTable('payslips')
            ? (float) DB::table('payslips')
                ->where('status', 'paid')
                ->whereDate('payment_date', '>=', $monthStart)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('net_salary')
            : 0;

        $monthlySpend = $expenses + $payroll;

        if ($monthlySpend <= 0) {
            // No spend data — score based on absolute liquidity buckets.
            $score = match (true) {
                $liquid > 500000 => 20,
                $liquid > 100000 => 14,
                $liquid > 20000  => 8,
                $liquid > 0      => 4,
                default          => 0,
            };
        } else {
            // Months of runway: liquid ÷ monthly spend.
            $runway = $liquid / $monthlySpend;
            $score = match (true) {
                $runway >= 6   => 20,
                $runway >= 3   => 14,
                $runway >= 1.5 => 8,
                $runway >= 0.5 => 4,
                default        => 0,
            };
        }

        return [
            'score'   => $score,
            'label'   => 'Cash position',
            'liquid'  => $liquid,
            'monthly_spend' => $monthlySpend,
            'note'    => $liquid > 0
                ? "Liquidity: " . number_format($liquid, 0)
                : 'Cash position is negative',
        ];
    }

    /**
     * 15 pts max. Outstanding receivables vs total billed.
     *   - outstanding <= 10% billed  → 15 pts
     *   - outstanding <= 25% billed  → 11 pts
     *   - outstanding <= 50% billed  → 6 pts
     *   - outstanding >  50% billed  → 2 pts
     */
    private function scoreReceivables(?int $branchId): array
    {
        if (!Schema::hasTable('sales')) {
            return ['score' => 7.5, 'label' => 'Receivables', 'outstanding_pct' => null, 'note' => 'No data'];
        }

        $row = DB::table('sales')
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total, COALESCE(SUM(due_amount), 0) as due')
            ->first();

        $total = (float) ($row->total ?? 0);
        $due   = (float) ($row->due   ?? 0);

        if ($total <= 0) {
            return ['score' => 7.5, 'label' => 'Receivables', 'outstanding_pct' => null, 'note' => 'No active invoices'];
        }

        $pct = ($due / $total) * 100;
        $score = match (true) {
            $pct <= 10 => 15,
            $pct <= 25 => 11,
            $pct <= 50 => 6,
            default    => 2,
        };

        return [
            'score'           => $score,
            'label'           => 'Receivables',
            'outstanding'     => $due,
            'outstanding_pct' => round($pct, 1),
            'note'            => round($pct, 1) . "% of billed outstanding",
        ];
    }

    /**
     * 20 pts max. Operational risk = open critical notifications + low
     * stock count. Anything > 0 pulls the score down sharply because
     * these signal "do something today".
     *   - 0 critical AND low_stock < 5  → 20 pts
     *   - 0 critical AND low_stock < 15 → 14 pts
     *   - 1 critical OR low_stock 15-30 → 8 pts
     *   - 2+ critical OR low_stock 30+  → 3 pts
     */
    private function scoreRisk(?int $branchId): array
    {
        $criticalCount = 0;
        if (Schema::hasTable('smart_notifications')) {
            $criticalCount = DB::table('smart_notifications')
                ->where('severity', 'critical')
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
        }

        $lowStock = 0;
        if (Schema::hasTable('inventory') && Schema::hasTable('products')) {
            $lowStock = DB::table('inventory as i')
                ->join('products as p', 'p.id', '=', 'i.product_id')
                ->where('p.is_active', true)
                ->whereNull('p.deleted_at')
                ->where('p.reorder_level', '>', 0)
                ->whereRaw('i.quantity <= p.reorder_level')
                ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
                ->count();
        }

        $score = match (true) {
            $criticalCount === 0 && $lowStock < 5  => 20,
            $criticalCount === 0 && $lowStock < 15 => 14,
            $criticalCount <= 1 || $lowStock < 30  => 8,
            default                                 => 3,
        };

        $notes = [];
        if ($criticalCount > 0) $notes[] = "{$criticalCount} critical alert(s)";
        if ($lowStock > 0)      $notes[] = "{$lowStock} low-stock product(s)";

        return [
            'score'    => $score,
            'label'    => 'Operational risk',
            'critical' => $criticalCount,
            'low_stock'=> $lowStock,
            'note'     => $notes ? implode(' · ', $notes) : 'All clear',
        ];
    }

    // ── Helpers (mirror DashboardController patterns) ────────────────────

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }

    /**
     * Mirrors DashboardController::accountingBalance so the score uses
     * exactly the same arithmetic the user sees in the Finance block.
     */
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
}
