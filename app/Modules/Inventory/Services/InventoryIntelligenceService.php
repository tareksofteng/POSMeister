<?php

namespace App\Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only intelligence on top of inventory + sales history.
 *
 * Movement is derived from sale_items joined to active sales, so the numbers
 * shift in real time as cashiers ring up sales. Inventory snapshot is read
 * from the `inventory` table (per-branch quantity).
 */
class InventoryIntelligenceService
{
    /** How many days of sales history defines "recent" by default. */
    public const LOOKBACK_DAYS = 30;

    /** A product with zero sales in this many days counts as dead stock. */
    public const DEAD_STOCK_DAYS = 90;

    /**
     * Dashboard payload — KPIs + classified product counts.
     */
    public function dashboard(?int $branchId = null, int $lookbackDays = self::LOOKBACK_DAYS): array
    {
        $rows = $this->movementSnapshot($branchId, $lookbackDays);

        $inventoryValue = 0;
        $low      = 0;
        $over     = 0;
        $dead     = 0;
        $fast     = 0;
        $medium   = 0;
        $slow     = 0;
        $units    = 0;
        $cogsLookback = 0;

        foreach ($rows as $r) {
            $qty = (float) $r->stock_qty;
            $units += $qty;
            $inventoryValue += $qty * (float) $r->cost_price;

            $cogsLookback += (float) $r->sold_qty * (float) $r->cost_price;

            if ($qty <= 0) {
                continue;
            }

            if ($qty <= (float) $r->reorder_level && $r->reorder_level > 0) {
                $low++;
            }

            $coverage = $this->coverageDays($qty, (float) $r->avg_daily_sales);
            if ($coverage !== null && $coverage > 180 && (float) $r->avg_daily_sales > 0) {
                $over++;
            }

            $class = $this->classify($r, $qty);
            if ($class === 'dead_stock')   $dead++;
            elseif ($class === 'fast_moving')   $fast++;
            elseif ($class === 'medium_moving') $medium++;
            elseif ($class === 'slow_moving')   $slow++;
        }

        $avgCoverage = $this->medianCoverage($rows);
        $turnover    = $inventoryValue > 0 ? round(($cogsLookback / $inventoryValue) * (365 / $lookbackDays), 2) : 0;

        return [
            'as_of'              => Carbon::today()->toDateString(),
            'lookback_days'      => $lookbackDays,
            'inventory_value'    => round($inventoryValue, 2),
            'total_units'        => round($units, 2),
            'distinct_products'  => count($rows),
            'low_stock_count'    => $low,
            'overstock_count'    => $over,
            'dead_stock_count'   => $dead,
            'fast_moving_count'  => $fast,
            'medium_moving_count'=> $medium,
            'slow_moving_count'  => $slow,
            'turnover_ratio'     => $turnover,
            'avg_coverage_days'  => $avgCoverage,
            'top_profitable'     => $this->topProfitable($rows, 10),
            'top_loss_risk'      => $this->topLossRisk($rows, 10),
        ];
    }

    /**
     * Classify all products into fast/medium/slow/dead movement bands.
     * Returns the full classified list — callers paginate or filter.
     */
    public function movementClassification(?int $branchId = null, int $lookbackDays = self::LOOKBACK_DAYS): array
    {
        $rows = $this->movementSnapshot($branchId, $lookbackDays);

        return array_map(function ($r) use ($lookbackDays) {
            $qty = (float) $r->stock_qty;
            $avgDaily = (float) $r->avg_daily_sales;
            return [
                'product_id'         => (int) $r->product_id,
                'sku'                => $r->sku,
                'name'               => $r->name,
                'branch_id'          => isset($r->branch_id) ? (int) $r->branch_id : null,
                'stock_qty'          => round($qty, 2),
                'cost_price'         => round((float) $r->cost_price, 2),
                'selling_price'      => round((float) $r->selling_price, 2),
                'reorder_level'      => round((float) $r->reorder_level, 2),
                'sold_qty'           => round((float) $r->sold_qty, 2),
                'avg_daily_sales'    => round($avgDaily, 3),
                'last_sale_date'     => $r->last_sale_date,
                'days_since_sale'    => $r->last_sale_date
                    ? (int) Carbon::parse($r->last_sale_date)->diffInDays(Carbon::today())
                    : null,
                'coverage_days'      => $this->coverageDays($qty, $avgDaily),
                'stock_value'        => round($qty * (float) $r->cost_price, 2),
                'classification'     => $this->classify($r, $qty),
                'lookback_days'      => $lookbackDays,
            ];
        }, $rows);
    }

    /**
     * Dead stock list — products with no sales in the dead-stock window
     * AND a non-zero on-hand quantity (so we can actually de-risk it).
     */
    public function deadStock(?int $branchId = null, int $deadDays = self::DEAD_STOCK_DAYS): array
    {
        $rows = $this->movementSnapshot($branchId, max($deadDays, self::LOOKBACK_DAYS));
        $today = Carbon::today();

        $out = [];
        foreach ($rows as $r) {
            $qty = (float) $r->stock_qty;
            if ($qty <= 0) continue;

            $daysSince = $r->last_sale_date
                ? (int) Carbon::parse($r->last_sale_date)->diffInDays($today)
                : null;

            $isDead = $daysSince === null || $daysSince >= $deadDays;
            if (!$isDead) continue;

            $out[] = [
                'product_id'      => (int) $r->product_id,
                'sku'             => $r->sku,
                'name'            => $r->name,
                'stock_qty'       => round($qty, 2),
                'stock_value'     => round($qty * (float) $r->cost_price, 2),
                'last_sale_date'  => $r->last_sale_date,
                'days_since_sale' => $daysSince,
                'cost_price'      => round((float) $r->cost_price, 2),
            ];
        }

        usort($out, fn($a, $b) => $b['stock_value'] <=> $a['stock_value']);
        return $out;
    }

    /**
     * Inventory aging — buckets by days since last sale.
     */
    public function aging(?int $branchId = null): array
    {
        $rows = $this->movementSnapshot($branchId, self::DEAD_STOCK_DAYS * 2);
        $today = Carbon::today();
        $buckets = [
            '0-30'   => ['count' => 0, 'value' => 0],
            '31-60'  => ['count' => 0, 'value' => 0],
            '61-90'  => ['count' => 0, 'value' => 0],
            '91-180' => ['count' => 0, 'value' => 0],
            '180+'   => ['count' => 0, 'value' => 0],
            'never'  => ['count' => 0, 'value' => 0],
        ];

        foreach ($rows as $r) {
            $qty = (float) $r->stock_qty;
            if ($qty <= 0) continue;
            $value = $qty * (float) $r->cost_price;

            if (!$r->last_sale_date) {
                $buckets['never']['count']++;
                $buckets['never']['value'] += $value;
                continue;
            }
            $days = (int) Carbon::parse($r->last_sale_date)->diffInDays($today);

            if      ($days <= 30)  $key = '0-30';
            elseif  ($days <= 60)  $key = '31-60';
            elseif  ($days <= 90)  $key = '61-90';
            elseif  ($days <= 180) $key = '91-180';
            else                   $key = '180+';

            $buckets[$key]['count']++;
            $buckets[$key]['value'] += $value;
        }

        foreach ($buckets as &$b) $b['value'] = round($b['value'], 2);
        return $buckets;
    }

    /**
     * Per-branch inventory health summary. Admin sees all branches, others
     * see just their own.
     */
    public function branchHealth(): array
    {
        if (!$this->isAdmin()) {
            $branchId = Auth::user()?->branch_id;
            return $branchId ? [$this->singleBranchHealth($branchId)] : [];
        }

        $branches = DB::table('branches')->where('is_active', true)
            ->select('id', 'name')->orderBy('name')->get();

        return $branches->map(fn($b) => $this->singleBranchHealth($b->id, $b->name))->all();
    }

    /**
     * Joined product + inventory + sales-derived movement.
     */
    private function movementSnapshot(?int $branchId, int $lookbackDays): array
    {
        $from = Carbon::today()->subDays($lookbackDays)->toDateString();
        $effectiveBranch = $this->resolveBranchScope($branchId);

        $q = DB::table('products as p')
            ->leftJoin('inventory as i', function ($j) use ($effectiveBranch) {
                $j->on('i.product_id', '=', 'p.id');
                if ($effectiveBranch) {
                    $j->where('i.branch_id', '=', $effectiveBranch);
                }
            })
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->selectRaw('
                p.id as product_id,
                p.sku,
                p.name,
                p.cost_price,
                p.selling_price,
                p.reorder_level,
                COALESCE(SUM(i.quantity), 0) as stock_qty,
                (
                    SELECT COALESCE(SUM(si.quantity), 0)
                    FROM sale_items si
                    INNER JOIN sales s ON s.id = si.sale_id
                    WHERE si.product_id = p.id
                      AND s.status = "active"
                      AND s.sale_date >= ?
                      ' . ($effectiveBranch ? 'AND s.branch_id = ' . (int) $effectiveBranch : '') . '
                ) as sold_qty,
                (
                    SELECT MAX(s.sale_date)
                    FROM sale_items si
                    INNER JOIN sales s ON s.id = si.sale_id
                    WHERE si.product_id = p.id
                      AND s.status = "active"
                      ' . ($effectiveBranch ? 'AND s.branch_id = ' . (int) $effectiveBranch : '') . '
                ) as last_sale_date
            ', [$from])
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.cost_price', 'p.selling_price', 'p.reorder_level');

        $rows = $q->get()->all();

        foreach ($rows as $r) {
            $r->avg_daily_sales = $lookbackDays > 0 ? ((float) $r->sold_qty / $lookbackDays) : 0;
        }
        return $rows;
    }

    private function singleBranchHealth(int $branchId, ?string $branchName = null): array
    {
        if (!$branchName) {
            $branchName = DB::table('branches')->where('id', $branchId)->value('name') ?? '—';
        }
        $d = $this->dashboard($branchId);
        return [
            'branch_id'        => $branchId,
            'branch_name'      => $branchName,
            'inventory_value'  => $d['inventory_value'],
            'low_stock_count'  => $d['low_stock_count'],
            'dead_stock_count' => $d['dead_stock_count'],
            'turnover_ratio'   => $d['turnover_ratio'],
            'health_score'     => $this->healthScore($d),
        ];
    }

    /**
     * 0..100 health score. Penalises dead stock + low stock heavily,
     * rewards a healthy turnover ratio. Rough heuristic, not gospel.
     */
    private function healthScore(array $d): int
    {
        $distinct = max(1, $d['distinct_products']);
        $deadPct  = $d['dead_stock_count'] / $distinct;
        $lowPct   = $d['low_stock_count']  / $distinct;
        $turnover = (float) $d['turnover_ratio'];

        $score = 100;
        $score -= min(40, $deadPct * 100);     // up to -40 from dead stock
        $score -= min(25, $lowPct  * 100);     // up to -25 from low stock
        if ($turnover < 2)  $score -= 15;       // sluggish turnover
        if ($turnover > 6)  $score += 5;        // healthy turnover
        return (int) max(0, min(100, $score));
    }

    private function classify(object $r, float $qty): string
    {
        $avgDaily = (float) $r->avg_daily_sales;
        $today = Carbon::today();
        $daysSince = $r->last_sale_date
            ? (int) Carbon::parse($r->last_sale_date)->diffInDays($today)
            : 9999;

        if ($qty > 0 && $daysSince >= self::DEAD_STOCK_DAYS) return 'dead_stock';
        if ($avgDaily >= 1.0)         return 'fast_moving';
        if ($avgDaily >= 0.2)         return 'medium_moving';
        if ($avgDaily > 0)            return 'slow_moving';
        return 'slow_moving';
    }

    private function coverageDays(float $qty, float $avgDaily): ?int
    {
        if ($avgDaily <= 0) return null;
        return (int) floor($qty / $avgDaily);
    }

    private function medianCoverage(array $rows): ?int
    {
        $coverages = [];
        foreach ($rows as $r) {
            $c = $this->coverageDays((float) $r->stock_qty, (float) $r->avg_daily_sales);
            if ($c !== null) $coverages[] = $c;
        }
        if (empty($coverages)) return null;
        sort($coverages);
        return (int) $coverages[(int) floor(count($coverages) / 2)];
    }

    private function topProfitable(array $rows, int $limit): array
    {
        $list = [];
        foreach ($rows as $r) {
            $margin = (float) $r->selling_price - (float) $r->cost_price;
            $profit = $margin * (float) $r->sold_qty;
            if ($profit <= 0) continue;
            $list[] = [
                'product_id' => (int) $r->product_id,
                'sku'        => $r->sku,
                'name'       => $r->name,
                'sold_qty'   => round((float) $r->sold_qty, 2),
                'unit_margin'=> round($margin, 2),
                'profit'     => round($profit, 2),
            ];
        }
        usort($list, fn($a, $b) => $b['profit'] <=> $a['profit']);
        return array_slice($list, 0, $limit);
    }

    private function topLossRisk(array $rows, int $limit): array
    {
        $list = [];
        $today = Carbon::today();
        foreach ($rows as $r) {
            $qty = (float) $r->stock_qty;
            if ($qty <= 0) continue;
            $value = $qty * (float) $r->cost_price;
            if ($value <= 0) continue;

            $daysSince = $r->last_sale_date
                ? (int) Carbon::parse($r->last_sale_date)->diffInDays($today)
                : 9999;

            // Risk score = capital tied up * staleness factor
            $staleness = min(1.0, $daysSince / 180.0);
            $risk = $value * $staleness;
            if ($risk <= 0) continue;

            $list[] = [
                'product_id'      => (int) $r->product_id,
                'sku'             => $r->sku,
                'name'             => $r->name,
                'stock_value'     => round($value, 2),
                'days_since_sale' => $r->last_sale_date ? $daysSince : null,
                'risk_score'      => round($risk, 2),
            ];
        }
        usort($list, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
        return array_slice($list, 0, $limit);
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if ($this->isAdmin()) {
            return $branchId; // admin can target any branch or all (null)
        }
        return Auth::user()?->branch_id;
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
