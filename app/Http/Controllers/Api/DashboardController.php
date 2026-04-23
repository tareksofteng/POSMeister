<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Inventory;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user     = auth()->user();
        $branchId = $user->branch_id; // null = admin sees all branches

        $today      = today();
        $monthStart = now()->startOfMonth()->toDateString();

        // ── Sales KPIs ────────────────────────────────────────────────────
        $todayRevenue = (float) Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->whereDate('sale_date', $today)
            ->sum('grand_total');

        $todaySalesCount = Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->whereDate('sale_date', $today)
            ->count();

        $monthRevenue = (float) Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->where('sale_date', '>=', $monthStart)
            ->sum('grand_total');

        // ── Customer KPIs ─────────────────────────────────────────────────
        $totalCustomers = Customer::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('is_active', true)
            ->count();

        // Total outstanding customer dues across all active sales
        $totalCustomerDue = (float) Sale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->sum('due_amount');

        // ── Inventory KPIs ────────────────────────────────────────────────
        $lowStockCount = Inventory::query()
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->when($branchId, fn($q) => $q->where('inventory.branch_id', $branchId))
            ->where('products.reorder_level', '>', 0)
            ->where('products.is_active', true)
            ->whereRaw('inventory.quantity <= products.reorder_level')
            ->whereNull('products.deleted_at')
            ->count();

        // ── Recent sales ──────────────────────────────────────────────────
        $recentSales = Sale::with('customer')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'sale_number', 'sale_date', 'customer_name', 'customer_type', 'customer_id', 'grand_total', 'total_paid', 'due_amount'])
            ->map(fn($s) => [
                'id'            => $s->id,
                'sale_number'   => $s->sale_number,
                'sale_date'     => $s->sale_date->format('Y-m-d'),
                'customer_name' => $s->customer?->name ?? $s->customer_name ?? 'Laufkunde',
                'grand_total'   => (float) $s->grand_total,
                'total_paid'    => (float) $s->total_paid,
                'due_amount'    => (float) $s->due_amount,
            ]);

        return response()->json([
            'today_revenue'      => $todayRevenue,
            'today_sales_count'  => $todaySalesCount,
            'month_revenue'      => $monthRevenue,
            'total_customers'    => $totalCustomers,
            'total_customer_due' => $totalCustomerDue,
            'low_stock_count'    => $lowStockCount,
            'recent_sales'       => $recentSales,
        ]);
    }
}
