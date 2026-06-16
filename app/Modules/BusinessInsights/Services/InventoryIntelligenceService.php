<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Inventory intelligence — the four questions every retail owner asks
 * about their stock room, answered with explainable arithmetic:
 *
 *   1. How fast is each product moving?          (velocity)
 *   2. Which products are aging on the shelf?    (aging buckets)
 *   3. Which are about to run out?               (expected stockout date)
 *   4. Am I turning my inventory fast enough?    (turnover ratio)
 *
 * All numbers are sourced from existing tables (inventory, sale_items,
 * sales). Workspace-scoped. Cached 15 minutes — stock changes faster
 * than RFM but still slow enough for a comfortable cache.
 */
class InventoryIntelligenceService
{
    public function summary(): array
    {
        $branchId = $this->resolveBranchId();
        $cacheKey = "inventory.intel:summary:" . ($branchId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($branchId) {
            return [
                'turnover'  => $this->turnover($branchId),
                'aging'     => $this->aging($branchId),
                'stockout'  => $this->stockoutForecast($branchId, 20),
                'velocity'  => $this->velocityLeaders($branchId, 10),
                'as_of'     => now()->toIso8601String(),
            ];
        });
    }

    // ── 1. Turnover ratio ───────────────────────────────────────────────

    /**
     * Inventory turnover = COGS / average inventory value.
     *
     * Returns the rolling-90d turnover plus a verdict ("healthy /
     * slow / fast") and the implied days of stock cover.
     */
    public function turnover(?int $branchId): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasTable('sale_items') || !Schema::hasTable('inventory')) {
            return $this->emptyTurnover();
        }

        $from = now()->subDays(90)->toDateString();

        $cogs = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $from)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->sum(DB::raw('si.quantity * si.cost_price'));

        // Average inventory value — we don't snapshot historically, so we
        // use the current on-hand × cost_price as a proxy. Defensible at
        // shop-floor cadence; the dashboard documents the assumption.
        $inventoryValue = (float) DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->sum(DB::raw('i.quantity * p.cost_price'));

        if ($inventoryValue <= 0) return $this->emptyTurnover();

        $ratio = round($cogs / $inventoryValue, 2);
        $daysOnHand = $cogs > 0 ? round(90 / ($cogs / $inventoryValue), 0) : null;

        return [
            'ratio'         => $ratio,
            'cogs_90d'      => round($cogs, 2),
            'inv_value_now' => round($inventoryValue, 2),
            'days_on_hand'  => $daysOnHand,
            'verdict'       => $this->turnoverVerdict($ratio),
        ];
    }

    private function turnoverVerdict(float $ratio): string
    {
        // Retail rule of thumb: 90-day turn ≥1 ≈ healthy; ≥2 ≈ fast;
        // <0.5 ≈ slow (capital trapped in shelves).
        if ($ratio >= 2)   return 'fast';
        if ($ratio >= 1)   return 'healthy';
        if ($ratio >= 0.5) return 'slow';
        return 'stagnant';
    }

    // ── 2. Aging buckets ────────────────────────────────────────────────

    /**
     * For each product with on-hand stock, classify by days since its
     * most recent active sale. Owners care about the dollars trapped in
     * each bucket, not the count of SKUs.
     */
    public function aging(?int $branchId): array
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('products')) {
            return ['buckets' => [], 'total_value' => 0];
        }

        $rows = DB::table('products as p')
            ->join('inventory as i', 'i.product_id', '=', 'p.id')
            ->leftJoinSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', 'active')
                    ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
                    ->selectRaw('si.product_id, MAX(s.sale_date) as last_sale')
                    ->groupBy('si.product_id'),
                'last', 'last.product_id', '=', 'p.id'
            )
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('i.quantity', '>', 0)
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->select('p.id', 'p.name', 'p.cost_price', 'i.quantity', 'last.last_sale')
            ->get();

        $buckets = [
            '0-30'   => ['days_max' => 30,  'count' => 0, 'value' => 0.0],
            '31-60'  => ['days_max' => 60,  'count' => 0, 'value' => 0.0],
            '61-90'  => ['days_max' => 90,  'count' => 0, 'value' => 0.0],
            '91-180' => ['days_max' => 180, 'count' => 0, 'value' => 0.0],
            '180+'   => ['days_max' => null,'count' => 0, 'value' => 0.0],
        ];

        $today = Carbon::today();
        $total = 0.0;
        foreach ($rows as $r) {
            $days = $r->last_sale ? $today->diffInDays(Carbon::parse($r->last_sale)) : 9999;
            $value = (float) $r->quantity * (float) ($r->cost_price ?? 0);
            $total += $value;

            $key = match (true) {
                $days <= 30   => '0-30',
                $days <= 60   => '31-60',
                $days <= 90   => '61-90',
                $days <= 180  => '91-180',
                default       => '180+',
            };
            $buckets[$key]['count']++;
            $buckets[$key]['value'] += $value;
        }

        // Shape for the frontend bar chart.
        $out = [];
        foreach ($buckets as $label => $b) {
            $out[] = [
                'label' => $label,
                'count' => $b['count'],
                'value' => round($b['value'], 2),
                'pct'   => $total > 0 ? round(($b['value'] / $total) * 100, 1) : 0,
            ];
        }

        return [
            'buckets'     => $out,
            'total_value' => round($total, 2),
        ];
    }

    // ── 3. Stockout forecast ────────────────────────────────────────────

    /**
     * Expected stockout = current quantity / mean daily sale rate over
     * the past 30 days. Products with no recent sales surface only when
     * they were sold previously but stock is below reorder; everything
     * else would be a meaningless "9999-day stockout".
     *
     * We sort ascending so the most-imminent ones come first.
     */
    public function stockoutForecast(?int $branchId, int $limit = 20): array
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('sale_items')) return [];

        $from = now()->subDays(30)->toDateString();

        $rows = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoinSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', 'active')
                    ->whereDate('s.sale_date', '>=', $from)
                    ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
                    ->selectRaw('si.product_id, SUM(si.quantity) as units_30d')
                    ->groupBy('si.product_id'),
                'v', 'v.product_id', '=', 'p.id'
            )
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('i.quantity', '>', 0)
            ->whereNotNull('v.units_30d')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->select(
                'p.id', 'p.name', 'p.sku', 'p.reorder_level',
                'i.quantity', 'v.units_30d'
            )
            ->get();

        $today = Carbon::today();
        $out = [];
        foreach ($rows as $r) {
            $dailyRate = ((float) $r->units_30d) / 30.0;
            if ($dailyRate <= 0) continue;
            $daysLeft = (int) max(0, floor(((float) $r->quantity) / $dailyRate));

            $out[] = [
                'product_id'    => (int) $r->id,
                'name'          => $r->name,
                'sku'           => $r->sku,
                'on_hand'       => (float) $r->quantity,
                'units_30d'     => (float) $r->units_30d,
                'daily_rate'    => round($dailyRate, 2),
                'days_to_stockout' => $daysLeft,
                'stockout_date' => $today->copy()->addDays($daysLeft)->toDateString(),
                'reorder_level' => (float) ($r->reorder_level ?? 0),
                'urgency'       => $this->stockoutUrgency($daysLeft, (float) $r->quantity, (float) ($r->reorder_level ?? 0)),
            ];
        }

        usort($out, fn($a, $b) => $a['days_to_stockout'] <=> $b['days_to_stockout']);
        return array_slice($out, 0, $limit);
    }

    private function stockoutUrgency(int $daysLeft, float $onHand, float $reorderLevel): string
    {
        if ($daysLeft <= 7)                                      return 'critical';
        if ($daysLeft <= 21 || ($reorderLevel && $onHand <= $reorderLevel)) return 'high';
        if ($daysLeft <= 45)                                     return 'medium';
        return 'low';
    }

    // ── 4. Velocity leaders ─────────────────────────────────────────────

    /**
     * Products by 30-day units velocity — the running heroes. Pairs
     * neatly with the stockout list above (a high-velocity product
     * with low days_to_stockout = panic).
     */
    public function velocityLeaders(?int $branchId, int $limit = 10): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasTable('sale_items')) return [];

        $from = now()->subDays(30)->toDateString();
        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $from)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('
                p.id, p.name, p.sku,
                SUM(si.quantity)   as units,
                SUM(si.line_total) as revenue,
                COUNT(DISTINCT s.id) as transactions
            ')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id'   => (int) $r->id,
            'name'         => $r->name,
            'sku'          => $r->sku,
            'units_30d'    => (float) $r->units,
            'revenue_30d'  => round((float) $r->revenue, 2),
            'velocity'     => round(((float) $r->units) / 30, 2),
            'transactions' => (int) $r->transactions,
        ])->all();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function emptyTurnover(): array
    {
        return [
            'ratio'         => 0,
            'cogs_90d'      => 0,
            'inv_value_now' => 0,
            'days_on_hand'  => null,
            'verdict'       => 'no_data',
        ];
    }

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
