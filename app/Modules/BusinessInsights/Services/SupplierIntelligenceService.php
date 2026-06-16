<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Supplier intelligence. The procurement side of the same questions we
 * answer for customers:
 *
 *   1. Who are my best suppliers?         (rankings by spend + count)
 *   2. Who is becoming a risk?            (concentration analysis)
 *   3. Who is reliable?                   (lead time + payment perf.)
 *   4. Who has gone quiet?                (inactivity)
 *
 * Pure SQL. Workspace-scoped. Cached 30 minutes — supplier mix doesn't
 * shift hour-by-hour.
 */
class SupplierIntelligenceService
{
    public function summary(): array
    {
        $branchId = $this->resolveBranchId();
        $cacheKey = "supplier.intel:summary:" . ($branchId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($branchId) {
            return [
                'totals'      => $this->totals($branchId),
                'top'         => $this->topSuppliers($branchId, 10),
                'concentration' => $this->concentration($branchId),
                'lead_times'  => $this->leadTimes($branchId, 8),
                'inactive'    => $this->inactive($branchId, 8),
                'payment'     => $this->paymentPerformance($branchId, 8),
                'as_of'       => now()->toIso8601String(),
            ];
        });
    }

    // ── Top-line ────────────────────────────────────────────────────────

    private function totals(?int $branchId): array
    {
        if (!Schema::hasTable('suppliers')) {
            return ['suppliers_total' => 0, 'active_365d' => 0, 'spend_365d' => 0];
        }

        $total = DB::table('suppliers')->whereNull('deleted_at')->count();

        $cutoff = now()->subDays(365)->toDateString();
        $active = 0;
        $spend  = 0.0;
        if (Schema::hasTable('purchases')) {
            $active = (int) DB::table('purchases')
                ->whereDate('purchase_date', '>=', $cutoff)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->distinct('supplier_id')
                ->count('supplier_id');

            $spend = (float) DB::table('purchases')
                ->whereDate('purchase_date', '>=', $cutoff)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');
        }

        return [
            'suppliers_total' => $total,
            'active_365d'     => $active,
            'spend_365d'      => round($spend, 2),
        ];
    }

    // ── Top suppliers by spend ─────────────────────────────────────────

    public function topSuppliers(?int $branchId, int $limit = 10): array
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('suppliers')) return [];
        $cutoff = now()->subDays(365)->toDateString();

        $rows = DB::table('purchases as p')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereDate('p.purchase_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('p.branch_id', $branchId))
            ->whereNull('s.deleted_at')
            ->selectRaw('
                s.id, s.name, s.code,
                SUM(p.total_amount) as spend,
                COUNT(p.id) as purchases,
                MAX(p.purchase_date) as last_purchase
            ')
            ->groupBy('s.id', 's.name', 's.code')
            ->orderByDesc('spend')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'supplier_id'   => (int) $r->id,
            'name'          => $r->name,
            'code'          => $r->code,
            'spend_365d'    => round((float) $r->spend, 2),
            'purchases_365d'=> (int) $r->purchases,
            'last_purchase' => $r->last_purchase,
        ])->all();
    }

    // ── Concentration risk ─────────────────────────────────────────────

    /**
     * Lorenz-style concentration check. Returns the top supplier's
     * share of total spend and a verdict:
     *
     *   ≥50% — critical (single point of failure)
     *   ≥30% — high
     *   ≥15% — medium
     *   <15% — low
     *
     * Plus the share of the top 3 combined for a fuller picture.
     */
    public function concentration(?int $branchId): array
    {
        if (!Schema::hasTable('purchases')) {
            return ['top_share_pct' => 0, 'top3_share_pct' => 0, 'verdict' => 'no_data'];
        }
        $cutoff = now()->subDays(365)->toDateString();

        $per = DB::table('purchases')
            ->whereDate('purchase_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('supplier_id, SUM(total_amount) as spend')
            ->groupBy('supplier_id')
            ->orderByDesc('spend')
            ->get();

        $total = (float) $per->sum('spend');
        if ($total <= 0) return ['top_share_pct' => 0, 'top3_share_pct' => 0, 'verdict' => 'no_data'];

        $top  = (float) ($per->first()->spend ?? 0);
        $top3 = (float) $per->take(3)->sum('spend');

        $topPct  = round(($top  / $total) * 100, 1);
        $top3Pct = round(($top3 / $total) * 100, 1);

        $verdict = match (true) {
            $topPct >= 50 => 'critical',
            $topPct >= 30 => 'high',
            $topPct >= 15 => 'medium',
            default        => 'low',
        };

        // Name of the top supplier — context for the verdict.
        $topName = null;
        if ($per->isNotEmpty() && Schema::hasTable('suppliers')) {
            $topName = DB::table('suppliers')
                ->where('id', $per->first()->supplier_id)
                ->value('name');
        }

        return [
            'top_share_pct'  => $topPct,
            'top3_share_pct' => $top3Pct,
            'top_supplier'   => $topName,
            'verdict'        => $verdict,
        ];
    }

    // ── Lead time ───────────────────────────────────────────────────────

    /**
     * Average days between purchase_date and the first time the row
     * flips to 'received'. We use updated_at as a proxy for "received
     * on" — the PurchaseService stamps it when status changes. Cheap
     * and resilient to schema variations.
     */
    public function leadTimes(?int $branchId, int $limit = 8): array
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('suppliers')) return [];
        $cutoff = now()->subDays(365)->toDateString();

        $rows = DB::table('purchases as p')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('p.status', 'received')
            ->whereDate('p.purchase_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('p.branch_id', $branchId))
            ->whereNull('s.deleted_at')
            ->selectRaw('
                s.id, s.name, s.code,
                AVG(DATEDIFF(p.updated_at, p.purchase_date)) as avg_lead_days,
                COUNT(p.id) as receipts
            ')
            ->groupBy('s.id', 's.name', 's.code')
            ->having('receipts', '>=', 3)
            ->orderBy('avg_lead_days')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'supplier_id'   => (int) $r->id,
            'name'          => $r->name,
            'code'          => $r->code,
            'avg_lead_days' => round((float) $r->avg_lead_days, 1),
            'receipts'      => (int) $r->receipts,
            'verdict'       => $this->leadTimeVerdict((float) $r->avg_lead_days),
        ])->all();
    }

    private function leadTimeVerdict(float $days): string
    {
        if ($days <= 3)  return 'fast';
        if ($days <= 7)  return 'on_time';
        if ($days <= 14) return 'slow';
        return 'late';
    }

    // ── Inactive suppliers ──────────────────────────────────────────────

    /**
     * Suppliers with >=5 purchases historically but nothing in the last
     * 90 days. The "regular partner who went quiet" signal.
     */
    public function inactive(?int $branchId, int $limit = 8): array
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('suppliers')) return [];

        $rows = DB::table('purchases as p')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereNull('s.deleted_at')
            ->when($branchId, fn($q) => $q->where('p.branch_id', $branchId))
            ->selectRaw('
                s.id, s.name, s.code,
                COUNT(p.id) as total_purchases,
                MAX(p.purchase_date) as last_purchase
            ')
            ->groupBy('s.id', 's.name', 's.code')
            ->having('total_purchases', '>=', 5)
            ->get()
            ->filter(fn ($r) => $r->last_purchase < now()->subDays(90)->toDateString())
            ->sortByDesc('total_purchases')
            ->take($limit)
            ->values();

        $today = Carbon::today();
        return $rows->map(fn ($r) => [
            'supplier_id'      => (int) $r->id,
            'name'             => $r->name,
            'code'             => $r->code,
            'total_purchases'  => (int) $r->total_purchases,
            'last_purchase'    => $r->last_purchase,
            'days_since'       => $r->last_purchase
                ? $today->diffInDays(Carbon::parse($r->last_purchase))
                : null,
        ])->all();
    }

    // ── Payment performance ─────────────────────────────────────────────

    /**
     * On-time payment rate per supplier. "On-time" = paid_amount equals
     * total_amount and the most recent supplier_payments row is dated
     * within 30 days of the purchase_date (a flexible window — many
     * suppliers run net-30 / net-45 terms). We surface the bottom
     * performers to flag attention.
     */
    public function paymentPerformance(?int $branchId, int $limit = 8): array
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('suppliers')) return [];
        $paidCol = Schema::hasColumn('purchases', 'paid_amount') ? 'paid_amount' : 'total_paid';

        $rows = DB::table('purchases as p')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereNull('s.deleted_at')
            ->where('p.status', 'received')
            ->whereDate('p.purchase_date', '>=', now()->subDays(365)->toDateString())
            ->when($branchId, fn($q) => $q->where('p.branch_id', $branchId))
            ->selectRaw("
                s.id, s.name, s.code,
                COUNT(p.id) as invoices,
                SUM(CASE WHEN COALESCE({$paidCol}, 0) >= COALESCE(p.total_amount, 0) THEN 1 ELSE 0 END) as paid_in_full,
                SUM(p.total_amount) as billed,
                SUM(COALESCE({$paidCol}, 0)) as paid
            ")
            ->groupBy('s.id', 's.name', 's.code')
            ->having('invoices', '>=', 3)
            ->get();

        $shaped = $rows->map(function ($r) {
            $invoices = (int) $r->invoices;
            $paidPct  = $invoices > 0 ? round(($r->paid_in_full / $invoices) * 100, 1) : 0;
            $billed   = (float) $r->billed;
            $paid     = (float) $r->paid;
            return [
                'supplier_id'  => (int) $r->id,
                'name'         => $r->name,
                'code'         => $r->code,
                'invoices'     => $invoices,
                'paid_in_full' => (int) $r->paid_in_full,
                'paid_pct'     => $paidPct,
                'outstanding'  => round(max(0, $billed - $paid), 2),
                'verdict'      => $this->paymentVerdict($paidPct),
            ];
        });

        // Surface the laggards first — those are the ones the owner
        // needs to action this week.
        return $shaped
            ->sortBy('paid_pct')
            ->take($limit)
            ->values()
            ->all();
    }

    private function paymentVerdict(float $pct): string
    {
        if ($pct >= 90) return 'excellent';
        if ($pct >= 75) return 'good';
        if ($pct >= 50) return 'mixed';
        return 'poor';
    }

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
