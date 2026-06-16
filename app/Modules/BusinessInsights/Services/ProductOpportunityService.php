<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\Branch\Services\BranchContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Product opportunity engine. Three deterministic questions:
 *
 *   1. Which products are bought together?      (market basket)
 *   2. Which categories are growing?            (period-over-period)
 *   3. Where is the margin mix tilting?         (high vs low margin)
 *
 * The "frequently bought together" check uses a SQL self-join on
 * sale_items — efficient for typical small-shop volumes, and we cap
 * with a configurable minimum support threshold so we don't surface
 * pairs that only co-occurred once. Confidence + lift help the owner
 * tell "real association" from "Tuesday coincidence".
 *
 * Workspace-scoped. Cached 30 minutes.
 */
class ProductOpportunityService
{
    public function summary(): array
    {
        $branchId = $this->resolveBranchId();
        $cacheKey = "product.opportunity:summary:" . ($branchId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($branchId) {
            return [
                'fbt'             => $this->frequentlyBoughtTogether($branchId, 15, 3),
                'category_growth' => $this->categoryGrowth($branchId),
                'margin_mix'      => $this->marginMix($branchId),
                'as_of'           => now()->toIso8601String(),
            ];
        });
    }

    // ── Frequently Bought Together (market basket) ──────────────────────

    /**
     * Returns the top product PAIRS that appear in the same sale, with
     * support + confidence + lift so the owner can judge how meaningful
     * each pairing actually is.
     *
     *   support     = pair_count / total_baskets
     *   confidence  = pair_count / product_a_count   (A → B)
     *   lift        = confidence / (product_b_count / total_baskets)
     *                 — lift > 1 means the pair occurs more often
     *                 together than chance would predict
     *
     * Minimum support threshold (`$minCo`) filters out one-off pairs.
     */
    public function frequentlyBoughtTogether(?int $branchId, int $limit = 15, int $minCo = 3): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) return [];
        $cutoff = now()->subDays(180)->toDateString();

        // Total basket count over the window — denominator for support.
        $totalBaskets = (int) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        if ($totalBaskets === 0) return [];

        // Single-product basket counts — needed for confidence + lift.
        $perProduct = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('si.product_id, COUNT(DISTINCT s.id) as baskets')
            ->groupBy('si.product_id')
            ->pluck('baskets', 'product_id')
            ->all();

        // Pair counts via a self-join. The b.product_id > a.product_id
        // condition keeps each unordered pair single-entry. We only
        // surface pairs above the support floor for noise control.
        $pairs = DB::table('sale_items as a')
            ->join('sale_items as b', function ($j) {
                $j->on('b.sale_id', '=', 'a.sale_id')
                  ->whereColumn('b.product_id', '>', 'a.product_id');
            })
            ->join('sales as s', 's.id', '=', 'a.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('a.product_id as a_id, b.product_id as b_id, COUNT(DISTINCT s.id) as together')
            ->groupBy('a.product_id', 'b.product_id')
            ->havingRaw('together >= ?', [$minCo])
            ->orderByDesc('together')
            ->limit($limit * 3)     // headroom — we'll re-sort by lift below
            ->get();

        if ($pairs->isEmpty()) return [];

        // Look up product names + skus once.
        $ids = $pairs->pluck('a_id')->merge($pairs->pluck('b_id'))->unique();
        $products = DB::table('products')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'sku'])
            ->keyBy('id');

        $shaped = [];
        foreach ($pairs as $p) {
            $aBaskets = (int) ($perProduct[$p->a_id] ?? 0);
            $bBaskets = (int) ($perProduct[$p->b_id] ?? 0);
            if ($aBaskets === 0 || $bBaskets === 0) continue;

            $together   = (int) $p->together;
            $support    = $together / $totalBaskets;
            $confidence = $together / $aBaskets;
            $expected   = $bBaskets / $totalBaskets;
            $lift       = $expected > 0 ? $confidence / $expected : 0;

            $shaped[] = [
                'product_a' => [
                    'id'   => (int) $p->a_id,
                    'name' => $products[$p->a_id]->name ?? '—',
                    'sku'  => $products[$p->a_id]->sku  ?? null,
                ],
                'product_b' => [
                    'id'   => (int) $p->b_id,
                    'name' => $products[$p->b_id]->name ?? '—',
                    'sku'  => $products[$p->b_id]->sku  ?? null,
                ],
                'together_count' => $together,
                'support_pct'    => round($support    * 100, 2),
                'confidence_pct' => round($confidence * 100, 1),
                'lift'           => round($lift, 2),
                'verdict'        => $this->liftVerdict($lift, $together),
            ];
        }

        // Re-sort by lift (then together_count as tiebreaker) so the
        // strongest associations come first.
        usort($shaped, function ($x, $y) {
            if ($x['lift'] === $y['lift']) {
                return $y['together_count'] <=> $x['together_count'];
            }
            return $y['lift'] <=> $x['lift'];
        });

        return array_slice($shaped, 0, $limit);
    }

    private function liftVerdict(float $lift, int $count): string
    {
        if ($lift >= 3 && $count >= 5)  return 'strong';
        if ($lift >= 1.5)               return 'meaningful';
        if ($lift >= 1)                 return 'mild';
        return 'weak';
    }

    // ── Category growth ─────────────────────────────────────────────────

    /**
     * Per-category revenue for the last 30 days vs the prior 30. Highest
     * positive delta first; categories below a threshold (a few hundred)
     * are excluded so the dashboard doesn't celebrate a 600% jump on a
     * tiny base.
     */
    public function categoryGrowth(?int $branchId): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('products')
            || !Schema::hasTable('product_categories') || !Schema::hasTable('sales')) {
            return [];
        }

        $current = $this->categoryRevenue($branchId, 30, 0);
        $prior   = $this->categoryRevenue($branchId, 60, 30);

        $out = [];
        foreach ($current as $catId => $row) {
            $now = (float) $row['revenue'];
            $was = (float) ($prior[$catId]['revenue'] ?? 0);
            if ($now < 500 && $was < 500) continue;     // suppress tiny bases

            $deltaPct = $was > 0 ? round((($now - $was) / $was) * 100, 1) : null;
            $out[] = [
                'category_id' => $catId,
                'name'        => $row['name'],
                'now'         => round($now, 2),
                'was'         => round($was, 2),
                'delta_pct'   => $deltaPct,
                'verdict'     => $this->growthVerdict($deltaPct),
            ];
        }

        usort($out, fn ($a, $b) => ($b['delta_pct'] ?? -999) <=> ($a['delta_pct'] ?? -999));
        return array_slice($out, 0, 10);
    }

    private function categoryRevenue(?int $branchId, int $daysFromNow, int $offset): array
    {
        $end   = now()->subDays($offset)->toDateString();
        $start = now()->subDays($daysFromNow)->toDateString();

        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->join('product_categories as c', 'c.id', '=', 'p.category_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $start)
            ->whereDate('s.sale_date', '<', $end)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('c.id, c.name, SUM(si.line_total) as revenue')
            ->groupBy('c.id', 'c.name')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'category_id' => (int) $r->id,
                'name'        => $r->name,
                'revenue'     => (float) $r->revenue,
            ];
        }
        return $out;
    }

    private function growthVerdict(?float $delta): string
    {
        if ($delta === null)  return 'new';
        if ($delta >=  20)    return 'growing';
        if ($delta >=  -5)    return 'steady';
        if ($delta >= -20)    return 'softening';
        return 'declining';
    }

    // ── Margin mix ──────────────────────────────────────────────────────

    /**
     * Tilts the owner's gut feel into numbers — what share of last
     * month's revenue came from each margin band. Categories of margin:
     *
     *   high    >= 35% gross margin
     *   medium  >= 15% gross margin
     *   low     >=  0% gross margin
     *   loss    <   0% (selling below cost)
     */
    public function marginMix(?int $branchId): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) return [];

        $cutoff = now()->subDays(30)->toDateString();
        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $cutoff)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('
                si.product_id,
                SUM(si.line_total)                  as revenue,
                SUM(si.quantity * si.cost_price)    as cogs
            ')
            ->groupBy('si.product_id')
            ->get();

        $bands = [
            'high'   => ['revenue' => 0.0, 'products' => 0],
            'medium' => ['revenue' => 0.0, 'products' => 0],
            'low'    => ['revenue' => 0.0, 'products' => 0],
            'loss'   => ['revenue' => 0.0, 'products' => 0],
        ];

        $total = 0.0;
        foreach ($rows as $r) {
            $revenue = (float) $r->revenue;
            $cogs    = (float) $r->cogs;
            if ($revenue <= 0) continue;
            $margin  = (($revenue - $cogs) / $revenue) * 100;
            $total  += $revenue;

            $key = match (true) {
                $margin >= 35  => 'high',
                $margin >= 15  => 'medium',
                $margin >=  0  => 'low',
                default         => 'loss',
            };
            $bands[$key]['revenue']  += $revenue;
            $bands[$key]['products']++;
        }

        $out = [];
        foreach ($bands as $key => $b) {
            $out[] = [
                'band'     => $key,
                'revenue'  => round($b['revenue'], 2),
                'products' => $b['products'],
                'pct'      => $total > 0 ? round(($b['revenue'] / $total) * 100, 1) : 0,
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
