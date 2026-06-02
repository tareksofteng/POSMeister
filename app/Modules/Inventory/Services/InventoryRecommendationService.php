<?php

namespace App\Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The reorder engine. Produces procurement suggestions based on velocity,
 * lead time and safety stock — pure read, no DB writes.
 *
 * Formula:
 *   recommended_qty = (avg_daily_sales * lead_time_days)
 *                   + safety_stock
 *                   - current_stock
 *
 *   safety_stock    = avg_daily_sales * safety_days
 *
 * Suggestions are deterministic: same inputs → same output. Callers can
 * cache the result or call on demand.
 */
class InventoryRecommendationService
{
    /** Sales history used to compute average daily velocity. */
    public const VELOCITY_DAYS = 30;

    /** Default lead time when a product has no historical supplier. */
    public const DEFAULT_LEAD_TIME_DAYS = 7;

    /** Buffer days of demand kept on top of the lead-time pipeline. */
    public const SAFETY_DAYS = 7;

    public function suggestions(?int $branchId = null, array $options = []): array
    {
        $velocityDays = $options['velocity_days'] ?? self::VELOCITY_DAYS;
        $safetyDays   = $options['safety_days']   ?? self::SAFETY_DAYS;
        $urgentOnly   = (bool) ($options['urgent_only'] ?? false);

        $effectiveBranch = $this->resolveBranchScope($branchId);
        $from = Carbon::today()->subDays($velocityDays)->toDateString();

        $rows = DB::table('products as p')
            ->join('inventory as i', function ($j) use ($effectiveBranch) {
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
                p.is_service,
                COALESCE(SUM(i.quantity), 0) as stock_qty,
                (
                    SELECT COALESCE(SUM(si.quantity), 0)
                    FROM sale_items si
                    INNER JOIN sales s ON s.id = si.sale_id
                    WHERE si.product_id = p.id
                      AND s.status = "active"
                      AND s.sale_date >= ?
                      ' . ($effectiveBranch ? 'AND s.branch_id = ' . (int) $effectiveBranch : '') . '
                ) as sold_qty
            ', [$from])
            ->where('p.is_service', false)
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.cost_price', 'p.selling_price', 'p.reorder_level', 'p.is_service')
            ->get();

        $today = Carbon::today();
        $out = [];

        foreach ($rows as $r) {
            $stock     = (float) $r->stock_qty;
            $sold      = (float) $r->sold_qty;
            $avgDaily  = $velocityDays > 0 ? $sold / $velocityDays : 0;
            $reorderAt = (float) $r->reorder_level;

            // Need to reorder if either: below reorder level OR projected to
            // stock out within (lead_time + safety_days)
            $supplier = $this->preferredSupplier($r->product_id);
            $leadTime = $supplier?->lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS;

            $safetyStock = $avgDaily * $safetyDays;
            $reorderPoint = ($avgDaily * $leadTime) + $safetyStock;

            $belowReorderLevel = $reorderAt > 0 && $stock <= $reorderAt;
            $belowReorderPoint = $stock <= $reorderPoint && $avgDaily > 0;

            if (!$belowReorderLevel && !$belowReorderPoint) {
                continue;
            }

            $recommendedQty = max(0, ($avgDaily * $leadTime) + $safetyStock - $stock);
            // Round up to whole units — you can't order 3.7 widgets.
            $recommendedQty = (int) ceil($recommendedQty);
            if ($recommendedQty <= 0) {
                // Already under reorder level but no velocity → minimal reorder
                $recommendedQty = (int) max(1, ceil(max(0, $reorderAt - $stock)));
            }

            $coverageDays = $avgDaily > 0 ? (int) floor($stock / $avgDaily) : null;
            $stockoutDate = $avgDaily > 0
                ? Carbon::today()->addDays($coverageDays)->toDateString()
                : null;

            $urgency = $this->urgency($stock, $avgDaily, $leadTime, $reorderAt);
            if ($urgentOnly && $urgency === 'low') continue;

            $lastPrice = $this->lastPurchasePrice($r->product_id, $supplier?->id);

            $out[] = [
                'product_id'         => (int) $r->product_id,
                'sku'                => $r->sku,
                'name'               => $r->name,
                'current_stock'      => round($stock, 2),
                'reorder_level'      => round($reorderAt, 2),
                'avg_daily_sales'    => round($avgDaily, 3),
                'coverage_days'      => $coverageDays,
                'predicted_stockout' => $stockoutDate,
                'lead_time_days'     => (int) $leadTime,
                'safety_days'        => (int) $safetyDays,
                'recommended_qty'    => $recommendedQty,
                'cost_price'         => round((float) $r->cost_price, 2),
                'last_purchase_price'=> $lastPrice !== null ? round((float) $lastPrice, 2) : null,
                'estimated_cost'     => round($recommendedQty * (float) ($lastPrice ?? $r->cost_price), 2),
                'preferred_supplier' => $supplier ? [
                    'id'             => (int) $supplier->id,
                    'name'           => $supplier->name,
                    'lead_time_days' => (int) ($supplier->lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS),
                ] : null,
                'urgency'            => $urgency,
            ];
        }

        // Critical urgency first, then highest cash-at-risk descending.
        $weight = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0];
        usort($out, function ($a, $b) use ($weight) {
            $cmp = $weight[$b['urgency']] <=> $weight[$a['urgency']];
            if ($cmp !== 0) return $cmp;
            return ($b['estimated_cost'] ?? 0) <=> ($a['estimated_cost'] ?? 0);
        });

        return $out;
    }

    /**
     * Group suggestions by preferred supplier — useful for one-click
     * purchase order generation per supplier.
     */
    public function suggestionsBySupplier(?int $branchId = null, array $options = []): array
    {
        $items = $this->suggestions($branchId, $options);
        $grouped = [];

        foreach ($items as $it) {
            $supplierId   = $it['preferred_supplier']['id']   ?? 0;
            $supplierName = $it['preferred_supplier']['name'] ?? '—';

            if (!isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [
                    'supplier_id'   => $supplierId ?: null,
                    'supplier_name' => $supplierName,
                    'items'         => [],
                    'item_count'    => 0,
                    'total_qty'     => 0,
                    'estimated_total' => 0,
                ];
            }
            $grouped[$supplierId]['items'][]         = $it;
            $grouped[$supplierId]['item_count']     += 1;
            $grouped[$supplierId]['total_qty']      += $it['recommended_qty'];
            $grouped[$supplierId]['estimated_total']+= $it['estimated_cost'];
        }

        foreach ($grouped as &$g) {
            $g['estimated_total'] = round($g['estimated_total'], 2);
        }

        $out = array_values($grouped);
        usort($out, fn($a, $b) => $b['estimated_total'] <=> $a['estimated_total']);
        return $out;
    }

    /**
     * The supplier with the most recent purchase line for a given product.
     */
    private function preferredSupplier(int $productId): ?object
    {
        return DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->join('suppliers as s', 's.id', '=', 'pu.supplier_id')
            ->where('pi.product_id', $productId)
            ->whereNull('pu.deleted_at')
            ->orderByDesc('pu.purchase_date')
            ->orderByDesc('pu.id')
            ->select('s.id', 's.name', 's.lead_time_days')
            ->first();
    }

    private function lastPurchasePrice(int $productId, ?int $supplierId): ?float
    {
        $q = DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->where('pi.product_id', $productId)
            ->whereNull('pu.deleted_at');
        if ($supplierId) {
            $q->where('pu.supplier_id', $supplierId);
        }
        $row = $q->orderByDesc('pu.purchase_date')->orderByDesc('pu.id')->value('pi.unit_cost');
        return $row !== null ? (float) $row : null;
    }

    private function urgency(float $stock, float $avgDaily, int $leadTime, float $reorderAt): string
    {
        if ($avgDaily <= 0) {
            // No velocity — urgency only matters if we're already below reorder
            return $reorderAt > 0 && $stock <= $reorderAt ? 'low' : 'low';
        }

        $coverage = $stock / $avgDaily;

        if ($coverage <= $leadTime / 2) return 'critical';
        if ($coverage <= $leadTime)     return 'high';
        if ($coverage <= $leadTime + self::SAFETY_DAYS) return 'medium';
        return 'low';
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') {
            return $branchId;
        }
        return Auth::user()?->branch_id;
    }
}
