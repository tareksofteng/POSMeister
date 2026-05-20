<?php

namespace App\Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Heavier analytics: valuation, profitability per product, and a movement
 * report (units in vs units out) across a date range.
 */
class InventoryAnalyticsService
{
    public function valuation(?int $branchId = null): array
    {
        $effectiveBranch = $this->resolveBranchScope($branchId);

        $rows = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->when($effectiveBranch, fn($q) => $q->where('i.branch_id', $effectiveBranch))
            ->selectRaw('
                p.id as product_id, p.sku, p.name,
                SUM(i.quantity) as qty,
                p.cost_price, p.selling_price,
                SUM(i.quantity) * p.cost_price    as cost_value,
                SUM(i.quantity) * p.selling_price as retail_value
            ')
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.cost_price', 'p.selling_price')
            ->orderByDesc('cost_value')
            ->get();

        $totalCost   = 0;
        $totalRetail = 0;
        $items = $rows->map(function ($r) use (&$totalCost, &$totalRetail) {
            $cost   = round((float) $r->cost_value, 2);
            $retail = round((float) $r->retail_value, 2);
            $totalCost   += $cost;
            $totalRetail += $retail;
            return [
                'product_id'    => (int) $r->product_id,
                'sku'           => $r->sku,
                'name'          => $r->name,
                'qty'           => round((float) $r->qty, 2),
                'cost_price'    => round((float) $r->cost_price, 2),
                'selling_price' => round((float) $r->selling_price, 2),
                'cost_value'    => $cost,
                'retail_value'  => $retail,
            ];
        })->all();

        return [
            'as_of'              => Carbon::today()->toDateString(),
            'items'              => $items,
            'total_cost_value'   => round($totalCost, 2),
            'total_retail_value' => round($totalRetail, 2),
            'unrealised_margin'  => round($totalRetail - $totalCost, 2),
        ];
    }

    public function profitability(string $from, string $to, ?int $branchId = null): array
    {
        $effectiveBranch = $this->resolveBranchScope($branchId);

        $rows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereBetween('s.sale_date', [$from, $to])
            ->whereNull('p.deleted_at')
            ->when($effectiveBranch, fn($q) => $q->where('s.branch_id', $effectiveBranch))
            ->selectRaw('
                p.id as product_id, p.sku, p.name,
                SUM(si.quantity) as qty_sold,
                SUM(si.line_total) as revenue,
                SUM(si.quantity * si.cost_price) as cost
            ')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->get();

        return $rows->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cost    = (float) $r->cost;
            $profit  = $revenue - $cost;
            return [
                'product_id' => (int) $r->product_id,
                'sku'        => $r->sku,
                'name'       => $r->name,
                'qty_sold'   => round((float) $r->qty_sold, 2),
                'revenue'    => round($revenue, 2),
                'cost'       => round($cost, 2),
                'profit'     => round($profit, 2),
                'margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
            ];
        })->sortByDesc('profit')->values()->all();
    }

    /**
     * Movement = units in (purchases received) vs units out (sold) in a period.
     */
    public function movement(string $from, string $to, ?int $branchId = null): array
    {
        $effectiveBranch = $this->resolveBranchScope($branchId);

        $sold = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereBetween('s.sale_date', [$from, $to])
            ->when($effectiveBranch, fn($q) => $q->where('s.branch_id', $effectiveBranch))
            ->selectRaw('si.product_id, SUM(si.quantity) as qty')
            ->groupBy('si.product_id')
            ->pluck('qty', 'product_id');

        $received = DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->where('pu.status', 'received')
            ->whereNull('pu.deleted_at')
            ->whereBetween('pu.purchase_date', [$from, $to])
            ->when($effectiveBranch, fn($q) => $q->where('pu.branch_id', $effectiveBranch))
            ->selectRaw('pi.product_id, SUM(pi.quantity) as qty')
            ->groupBy('pi.product_id')
            ->pluck('qty', 'product_id');

        $productIds = $sold->keys()->merge($received->keys())->unique()->values();
        if ($productIds->isEmpty()) {
            return ['period' => ['from' => $from, 'to' => $to], 'rows' => []];
        }

        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $skus = DB::table('products')
            ->whereIn('id', $productIds)
            ->pluck('sku', 'id');

        $rows = $productIds->map(function ($pid) use ($sold, $received, $products, $skus) {
            $in  = (float) ($received[$pid] ?? 0);
            $out = (float) ($sold[$pid] ?? 0);
            return [
                'product_id' => (int) $pid,
                'sku'        => $skus[$pid] ?? '',
                'name'       => $products[$pid] ?? '',
                'qty_in'     => round($in, 2),
                'qty_out'    => round($out, 2),
                'net'        => round($in - $out, 2),
            ];
        })->sortByDesc('qty_out')->values()->all();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'rows'   => $rows,
        ];
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') {
            return $branchId;
        }
        return Auth::user()?->branch_id;
    }
}
