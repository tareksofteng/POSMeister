<?php

namespace App\Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Supplier-side analytics: how reliable they are, how often they ship,
 * what they tend to supply, and how their cost has moved over time.
 */
class SupplierAnalyticsService
{
    public function leaderboard(?string $from = null, ?string $to = null): array
    {
        [$from, $to] = $this->resolvePeriod($from, $to);

        $rows = DB::table('suppliers as s')
            ->leftJoin('purchases as pu', function ($j) use ($from, $to) {
                $j->on('pu.supplier_id', '=', 's.id')
                  ->where('pu.status', '=', 'received')
                  ->whereBetween('pu.purchase_date', [$from, $to])
                  ->whereNull('pu.deleted_at');
            })
            ->leftJoin('supplier_payments as sp', function ($j) use ($from, $to) {
                $j->on('sp.supplier_id', '=', 's.id')
                  ->whereBetween('sp.payment_date', [$from, $to]);
            })
            ->where('s.is_active', true)
            ->whereNull('s.deleted_at')
            ->selectRaw('
                s.id, s.name, s.lead_time_days,
                COUNT(DISTINCT pu.id) as purchase_count,
                COALESCE(SUM(pu.total_amount), 0) as total_purchased,
                COALESCE(SUM(sp.amount), 0) as total_paid,
                MAX(pu.purchase_date) as last_purchase_date
            ')
            ->groupBy('s.id', 's.name', 's.lead_time_days')
            ->orderByDesc('total_purchased')
            ->get();

        $today = Carbon::today();
        return $rows->map(fn($r) => [
            'supplier_id'        => (int) $r->id,
            'name'               => $r->name,
            'lead_time_days'     => (int) ($r->lead_time_days ?? 0),
            'purchase_count'     => (int) $r->purchase_count,
            'total_purchased'    => round((float) $r->total_purchased, 2),
            'total_paid'         => round((float) $r->total_paid, 2),
            'outstanding'        => round(max(0, (float) $r->total_purchased - (float) $r->total_paid), 2),
            'last_purchase_date' => $r->last_purchase_date,
            'days_since_last'    => $r->last_purchase_date
                ? (int) Carbon::parse($r->last_purchase_date)->diffInDays($today)
                : null,
        ])->all();
    }

    /**
     * Per-supplier detail: top products, monthly volume trend, cost trend.
     */
    public function show(int $supplierId, ?string $from = null, ?string $to = null): array
    {
        [$from, $to] = $this->resolvePeriod($from, $to, 6);

        $supplier = DB::table('suppliers')->where('id', $supplierId)->first();
        if (!$supplier) {
            return ['supplier' => null];
        }

        $topProducts = DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->where('pu.supplier_id', $supplierId)
            ->where('pu.status', 'received')
            ->whereNull('pu.deleted_at')
            ->whereBetween('pu.purchase_date', [$from, $to])
            ->selectRaw('
                p.id as product_id, p.sku, p.name,
                SUM(pi.quantity) as total_qty,
                SUM(pi.line_total) as total_value
            ')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $monthlyTrend = $this->monthlyTrend($supplierId, $from, $to);
        $costTrend    = $this->costTrend($supplierId, $from, $to);

        return [
            'supplier' => [
                'id'             => (int) $supplier->id,
                'name'           => $supplier->name,
                'code'           => $supplier->code,
                'lead_time_days' => (int) ($supplier->lead_time_days ?? 0),
                'phone'          => $supplier->phone,
                'email'          => $supplier->email,
            ],
            'period'        => ['from' => $from, 'to' => $to],
            'top_products'  => $topProducts->map(fn($r) => [
                'product_id'  => (int) $r->product_id,
                'sku'         => $r->sku,
                'name'        => $r->name,
                'total_qty'   => round((float) $r->total_qty, 2),
                'total_value' => round((float) $r->total_value, 2),
            ])->all(),
            'monthly_trend' => $monthlyTrend,
            'cost_trend'    => $costTrend,
        ];
    }

    private function monthlyTrend(int $supplierId, string $from, string $to): array
    {
        $rows = DB::table('purchases')
            ->where('supplier_id', $supplierId)
            ->where('status', 'received')
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->selectRaw('
                DATE_FORMAT(purchase_date, "%Y-%m") as ym,
                COUNT(*) as purchases,
                SUM(total_amount) as total
            ')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return $rows->map(fn($r) => [
            'month'     => $r->ym,
            'purchases' => (int) $r->purchases,
            'total'     => round((float) $r->total, 2),
        ])->all();
    }

    /**
     * Average unit cost per month for products this supplier provides.
     * Useful to spot price creep.
     */
    private function costTrend(int $supplierId, string $from, string $to): array
    {
        $rows = DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->where('pu.supplier_id', $supplierId)
            ->where('pu.status', 'received')
            ->whereNull('pu.deleted_at')
            ->whereBetween('pu.purchase_date', [$from, $to])
            ->selectRaw('
                DATE_FORMAT(pu.purchase_date, "%Y-%m") as ym,
                AVG(pi.unit_cost) as avg_cost,
                SUM(pi.quantity)  as total_qty
            ')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return $rows->map(fn($r) => [
            'month'     => $r->ym,
            'avg_cost'  => round((float) $r->avg_cost, 2),
            'total_qty' => round((float) $r->total_qty, 2),
        ])->all();
    }

    private function resolvePeriod(?string $from, ?string $to, int $defaultMonthsBack = 12): array
    {
        $to   = $to   ?: Carbon::today()->toDateString();
        $from = $from ?: Carbon::today()->subMonths($defaultMonthsBack)->startOfMonth()->toDateString();
        return [$from, $to];
    }
}
