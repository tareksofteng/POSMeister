<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | DashboardTiersService — Phase AC Round 3
 |--------------------------------------------------------------------------
 |
 | Powers the new "Top Products 2.0" and "Top Customers 2.0" panels on the
 | executive dashboard. Each panel has 4 tabs — this service exposes
 | one method per tab.
 |
 |   PRODUCTS:
 |     best      — best sellers (qty + revenue) MTD
 |     slow      — products that sold but moved less than 5 units MTD
 |     dead      — active products with no sale in 90+ days
 |     reorder   — products at or below reorder level
 |
 |   CUSTOMERS:
 |     vip          — top 5 by lifetime revenue (active customers only)
 |     recent       — last 5 customers who made a purchase
 |     outstanding  — highest outstanding due balance
 |     biggest      — biggest single purchase amount this month
 |
 | All queries scope through BranchContextService. Each tab is bounded
 | by a date window or a top-N limit so they stay fast on big tables.
 */
class DashboardTiersService
{
    public const PRODUCT_TABS  = ['best', 'slow', 'dead', 'reorder'];
    public const CUSTOMER_TABS = ['vip', 'recent', 'outstanding', 'biggest'];

    // ─────────────────────────────────────────────────────────────────────
    // Products
    // ─────────────────────────────────────────────────────────────────────

    public function products(string $tab, int $limit = 5): array
    {
        if (!in_array($tab, self::PRODUCT_TABS, true)) $tab = 'best';
        $branchId   = $this->resolveBranchId();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();

        return match ($tab) {
            'best'    => $this->productsBest($branchId, $monthStart, $limit),
            'slow'    => $this->productsSlow($branchId, $monthStart, $limit),
            'dead'    => $this->productsDead($branchId, $limit),
            'reorder' => $this->productsReorder($branchId, $limit),
        };
    }

    private function productsBest(?int $branchId, string $monthStart, int $limit): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) return [];

        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('p.id, p.name, p.sku, p.image,
                         SUM(si.quantity)   as qty_sold,
                         SUM(si.line_total) as revenue')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.image')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => $this->mapProduct($r) + [
                'metric_label' => 'qty_sold',
                'metric_value' => (float) $r->qty_sold,
                'revenue'      => (float) $r->revenue,
            ])
            ->all();
    }

    private function productsSlow(?int $branchId, string $monthStart, int $limit): array
    {
        if (!Schema::hasTable('sale_items') || !Schema::hasTable('sales')) return [];

        // Sold but few units — between 1 and 5 inclusive, MTD.
        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('p.id, p.name, p.sku, p.image, SUM(si.quantity) as qty_sold')
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.image')
            ->havingRaw('SUM(si.quantity) <= 5')
            ->orderBy('qty_sold')
            ->limit($limit)
            ->get()
            ->map(fn($r) => $this->mapProduct($r) + [
                'metric_label' => 'qty_sold',
                'metric_value' => (float) $r->qty_sold,
            ])
            ->all();
    }

    private function productsDead(?int $branchId, int $limit): array
    {
        if (!Schema::hasTable('products')) return [];
        $cutoff = now()->subDays(90)->toDateString();

        // Active products with stock > 0 (or any) and no active sale_item
        // in the last 90 days. Bounded join — cheap on bounded scans.
        return DB::table('products as p')
            ->leftJoinSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', 'active')
                    ->where('s.sale_date', '>=', $cutoff)
                    ->selectRaw('si.product_id, MAX(s.sale_date) as last_sale')
                    ->groupBy('si.product_id'),
                'l', 'l.product_id', '=', 'p.id'
            )
            ->when($branchId && Schema::hasTable('inventory'), function ($q) use ($branchId) {
                $q->whereExists(function ($sub) use ($branchId) {
                    $sub->select(DB::raw(1))
                        ->from('inventory')
                        ->whereColumn('inventory.product_id', 'p.id')
                        ->where('inventory.branch_id', $branchId)
                        ->where('inventory.quantity', '>', 0);
                });
            })
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->whereNull('l.last_sale')
            ->select('p.id', 'p.name', 'p.sku', 'p.image')
            ->orderBy('p.name')
            ->limit($limit)
            ->get()
            ->map(fn($r) => $this->mapProduct($r) + [
                'metric_label' => 'days_idle',
                'metric_value' => 90,
            ])
            ->all();
    }

    private function productsReorder(?int $branchId, int $limit): array
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('products')) return [];

        return DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('p.reorder_level', '>', 0)
            ->whereRaw('i.quantity <= p.reorder_level')
            ->when($branchId, fn($q) => $q->where('i.branch_id', $branchId))
            ->selectRaw('p.id, p.name, p.sku, p.image,
                         i.quantity as quantity,
                         p.reorder_level as reorder_level')
            ->orderByRaw('(p.reorder_level - i.quantity) DESC')
            ->limit($limit)
            ->get()
            ->map(fn($r) => $this->mapProduct($r) + [
                'metric_label' => 'shortfall',
                'metric_value' => max(0, (float) $r->reorder_level - (float) $r->quantity),
                'on_hand'      => (float) $r->quantity,
                'reorder_level'=> (float) $r->reorder_level,
            ])
            ->all();
    }

    private function mapProduct(object $r): array
    {
        return [
            'id'    => (int) $r->id,
            'name'  => $r->name,
            'sku'   => $r->sku ?? null,
            'image' => $r->image ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Customers
    // ─────────────────────────────────────────────────────────────────────

    public function customers(string $tab, int $limit = 5): array
    {
        if (!in_array($tab, self::CUSTOMER_TABS, true)) $tab = 'vip';
        $branchId   = $this->resolveBranchId();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();

        return match ($tab) {
            'vip'         => $this->customersVip($branchId, $limit),
            'recent'      => $this->customersRecent($branchId, $limit),
            'outstanding' => $this->customersOutstanding($branchId, $limit),
            'biggest'     => $this->customersBiggest($branchId, $monthStart, $limit),
        };
    }

    private function customersVip(?int $branchId, int $limit): array
    {
        if (!Schema::hasTable('customers') || !Schema::hasTable('sales')) return [];

        // Lifetime revenue. Active customers only. VIP tier badge if
        // lifetime revenue > 100k (matches the marketing definition).
        return DB::table('customers as c')
            ->leftJoin('sales as s', function ($j) use ($branchId) {
                $j->on('s.customer_id', '=', 'c.id')
                  ->where('s.status', 'active');
                if ($branchId) $j->where('s.branch_id', $branchId);
            })
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->selectRaw('c.id, c.name, c.code, c.phone,
                         COALESCE(SUM(s.grand_total), 0) as lifetime_revenue,
                         COUNT(s.id) as visits')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.phone')
            ->orderByDesc('lifetime_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => $this->mapCustomer($r) + [
                'tier'         => ((float) $r->lifetime_revenue) > 100000 ? 'vip' : 'regular',
                'metric_label' => 'lifetime_revenue',
                'metric_value' => (float) $r->lifetime_revenue,
                'visits'       => (int) $r->visits,
            ])
            ->all();
    }

    private function customersRecent(?int $branchId, int $limit): array
    {
        if (!Schema::hasTable('customers') || !Schema::hasTable('sales')) return [];

        // Customers whose MOST RECENT sale was nearest to today. We
        // join through a subquery so each customer surfaces only once.
        $sub = DB::table('sales')
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('customer_id, MAX(sale_date) as last_sale')
            ->groupBy('customer_id');

        return DB::table('customers as c')
            ->joinSub($sub, 'last', 'last.customer_id', '=', 'c.id')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->select('c.id', 'c.name', 'c.code', 'c.phone', 'last.last_sale')
            ->orderByDesc('last.last_sale')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => $this->mapCustomer($r) + [
                'tier'         => 'recent',
                'metric_label' => 'last_sale',
                'metric_value' => $r->last_sale,
            ])
            ->all();
    }

    private function customersOutstanding(?int $branchId, int $limit): array
    {
        if (!Schema::hasTable('customers') || !Schema::hasTable('sales')) return [];

        return DB::table('customers as c')
            ->join('sales as s', 's.customer_id', '=', 'c.id')
            ->where('s.status', 'active')
            ->whereRaw('COALESCE(s.grand_total, 0) > COALESCE(s.total_paid, 0)')
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->whereNull('c.deleted_at')
            ->selectRaw('c.id, c.name, c.code, c.phone,
                         SUM(s.grand_total - s.total_paid) as outstanding,
                         COUNT(s.id) as invoice_count')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.phone')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => $this->mapCustomer($r) + [
                'tier'          => 'overdue',
                'metric_label'  => 'outstanding',
                'metric_value'  => (float) $r->outstanding,
                'invoice_count' => (int) $r->invoice_count,
            ])
            ->all();
    }

    private function customersBiggest(?int $branchId, string $monthStart, int $limit): array
    {
        if (!Schema::hasTable('customers') || !Schema::hasTable('sales')) return [];

        return DB::table('customers as c')
            ->join('sales as s', 's.customer_id', '=', 'c.id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $monthStart)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->whereNull('c.deleted_at')
            ->selectRaw('c.id, c.name, c.code, c.phone,
                         MAX(s.grand_total) as biggest_sale,
                         s.sale_date as sale_date')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.phone', 's.sale_date')
            ->orderByDesc('biggest_sale')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => $this->mapCustomer($r) + [
                'tier'         => 'biggest',
                'metric_label' => 'biggest_sale',
                'metric_value' => (float) $r->biggest_sale,
                'sale_date'    => $r->sale_date,
            ])
            ->all();
    }

    private function mapCustomer(object $r): array
    {
        return [
            'id'      => (int) $r->id,
            'name'    => $r->name,
            'code'    => $r->code ?? null,
            'phone'   => $r->phone ?? null,
            'initials'=> $this->initials($r->name ?? ''),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
