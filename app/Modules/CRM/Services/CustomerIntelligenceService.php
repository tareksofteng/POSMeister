<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\LoyaltySettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics on top of customers + sales + loyalty + wallet.
 *
 * Segments are computed at query time from the live tables — we don't
 * materialise them. That keeps the model small and the segments always
 * fresh, at the cost of one heavier query per dashboard load.
 */
class CustomerIntelligenceService
{
    public const RECENT_DAYS = 30;
    public const INACTIVE_DAYS = 90;
    public const CHURN_RISK_DAYS = 60;

    public function dashboard(?int $branchId = null): array
    {
        $scope = $this->resolveBranchScope($branchId);
        $monthStart = Carbon::today()->startOfMonth()->toDateString();

        $totalActive = DB::table('customers')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->count();

        $newThisMonth = DB::table('customers')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $monthStart)
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->count();

        $salesAgg = DB::table('sales as s')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', Carbon::today()->subDays(self::RECENT_DAYS)->toDateString())
            ->when($scope, fn($q) => $q->where('s.branch_id', $scope))
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(grand_total), 0) as aov, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        $repeatPct = $this->repeatCustomerPercent($scope);
        $inactiveCount = $this->countSegment('inactive', $scope);
        $clv = $this->avgLifetimeValue($scope);

        $liabilities = DB::table('customer_loyalty_profiles as p')
            ->join('customers as c', 'c.id', '=', 'p.customer_id')
            ->when($scope, fn($q) => $q->where('c.branch_id', $scope))
            ->whereNull('c.deleted_at')
            ->sum('p.current_points');

        $settings = LoyaltySettings::current();
        $liabilityValue = round((float) $liabilities / max(1, $settings->redeem_points_per_currency), 2);

        return [
            'total_active_customers' => (int) $totalActive,
            'new_this_month'         => (int) $newThisMonth,
            'orders_recent'          => (int) $salesAgg->cnt,
            'avg_order_value'        => round((float) $salesAgg->aov, 2),
            'revenue_recent'         => round((float) $salesAgg->total, 2),
            'repeat_customer_pct'    => $repeatPct,
            'inactive_count'         => $inactiveCount,
            'avg_lifetime_value'     => round($clv, 2),
            'loyalty_points_outstanding' => round((float) $liabilities, 2),
            'loyalty_liability_value'    => $liabilityValue,
            'top_spending'           => $this->topSpending($scope, 10),
            'segment_counts'         => $this->segmentCounts($scope),
        ];
    }

    public function timeline(int $customerId): array
    {
        $customer = DB::table('customers')->where('id', $customerId)->first();
        if (!$customer) {
            return ['customer' => null, 'events' => []];
        }

        $sales = DB::table('sales')
            ->where('customer_id', $customerId)
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'sale_number', 'sale_date', 'grand_total', 'status', 'branch_id']);

        $returns = DB::table('sale_returns')
            ->where('customer_id', $customerId)
            ->orderByDesc('return_date')
            ->limit(50)
            ->get(['id', 'return_number', 'return_date', 'refund_amount']);

        $loyalty = DB::table('loyalty_transactions')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'type', 'points', 'balance_after', 'reference_number', 'note', 'created_at']);

        $wallet = DB::table('wallet_transactions')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'type', 'amount', 'balance_after', 'reference_number', 'note', 'created_at']);

        $payments = DB::table('customer_payments')
            ->where('customer_id', $customerId)
            ->orderByDesc('payment_date')
            ->limit(50)
            ->get(['id', 'amount', 'payment_method', 'payment_date', 'reference', 'note']);

        $events = [];
        foreach ($sales as $r) $events[] = [
            'at' => $r->sale_date, 'kind' => 'sale',
            'title' => 'Verkauf ' . $r->sale_number,
            'amount' => (float) $r->grand_total, 'meta' => ['status' => $r->status],
        ];
        foreach ($returns as $r) $events[] = [
            'at' => $r->return_date, 'kind' => 'return',
            'title' => 'Retoure ' . $r->return_number,
            'amount' => -(float) $r->refund_amount,
        ];
        foreach ($payments as $r) $events[] = [
            'at' => $r->payment_date, 'kind' => 'payment',
            'title' => 'Zahlung (' . $r->payment_method . ')',
            'amount' => (float) $r->amount, 'meta' => ['reference' => $r->reference],
        ];
        foreach ($loyalty as $r) $events[] = [
            'at' => substr((string) $r->created_at, 0, 10), 'kind' => 'loyalty',
            'title' => 'Treuepunkte ' . $r->type,
            'amount' => (float) $r->points, 'meta' => ['balance_after' => (float) $r->balance_after, 'note' => $r->note],
        ];
        foreach ($wallet as $r) $events[] = [
            'at' => substr((string) $r->created_at, 0, 10), 'kind' => 'wallet',
            'title' => 'Wallet ' . $r->type,
            'amount' => (float) $r->amount, 'meta' => ['balance_after' => (float) $r->balance_after, 'note' => $r->note],
        ];

        usort($events, fn($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));

        $behavior = $this->behavior($customerId);

        return [
            'customer'  => $customer,
            'behavior'  => $behavior,
            'events'    => array_slice($events, 0, 200),
        ];
    }

    /**
     * Per-customer behavioural signals — visit cadence, basket size,
     * favourite category / products.
     */
    public function behavior(int $customerId): array
    {
        $totals = DB::table('sales')
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->selectRaw('
                COUNT(*) as visits,
                COALESCE(AVG(grand_total), 0) as avg_basket,
                COALESCE(SUM(grand_total), 0) as lifetime_revenue,
                MIN(sale_date) as first_visit,
                MAX(sale_date) as last_visit
            ')
            ->first();

        $favCategory = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
            ->where('s.customer_id', $customerId)
            ->where('s.status', 'active')
            ->selectRaw('COALESCE(c.name, "—") as category_name, SUM(si.line_total) as total')
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->limit(1)
            ->value('category_name');

        $favProducts = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.customer_id', $customerId)
            ->where('s.status', 'active')
            ->selectRaw('p.id, p.name, p.sku, SUM(si.quantity) as qty, SUM(si.line_total) as total')
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $visitsPerMonth = null;
        if ($totals && $totals->first_visit && $totals->visits > 0) {
            $months = max(1, Carbon::parse($totals->first_visit)->diffInMonths(Carbon::today()) + 1);
            $visitsPerMonth = round($totals->visits / $months, 2);
        }

        return [
            'visits'             => (int) ($totals->visits ?? 0),
            'avg_basket'         => round((float) ($totals->avg_basket ?? 0), 2),
            'lifetime_revenue'   => round((float) ($totals->lifetime_revenue ?? 0), 2),
            'first_visit'        => $totals->first_visit ?? null,
            'last_visit'         => $totals->last_visit  ?? null,
            'days_since_last'    => $totals->last_visit
                ? (int) Carbon::parse($totals->last_visit)->diffInDays(Carbon::today())
                : null,
            'visits_per_month'   => $visitsPerMonth,
            'favourite_category' => $favCategory,
            'favourite_products' => $favProducts->map(fn($p) => [
                'product_id' => (int) $p->id,
                'name'       => $p->name,
                'sku'        => $p->sku,
                'qty'        => (float) $p->qty,
                'total'      => round((float) $p->total, 2),
            ])->all(),
        ];
    }

    /**
     * Listing of customers in a given segment.
     */
    public function segment(string $name, ?int $branchId = null, int $limit = 100): array
    {
        $scope = $this->resolveBranchScope($branchId);
        $q = $this->segmentBaseQuery($name, $scope);
        return $q->limit($limit)->get()->map(function ($r) {
            return [
                'customer_id'    => (int) $r->id,
                'name'           => $r->name,
                'phone'          => $r->phone,
                'email'          => $r->email,
                'tier'           => $r->tier ?? 'silver',
                'lifetime_spent' => round((float) ($r->lifetime_spent ?? 0), 2),
                'last_visit'     => $r->last_visit ?? null,
                'days_since'     => $r->last_visit
                    ? (int) Carbon::parse($r->last_visit)->diffInDays(Carbon::today())
                    : null,
            ];
        })->all();
    }

    public function segmentCounts(?int $branchId = null): array
    {
        $scope = $this->resolveBranchScope($branchId);
        return [
            'vip'               => $this->countSegment('vip', $scope),
            'inactive'          => $this->countSegment('inactive', $scope),
            'churn_risk'        => $this->countSegment('churn_risk', $scope),
            'discount_sensitive'=> $this->countSegment('discount_sensitive', $scope),
            'high_frequency'    => $this->countSegment('high_frequency', $scope),
        ];
    }

    private function segmentBaseQuery(string $name, ?int $branchId)
    {
        // Common base: customers joined to loyalty profile + their last sale.
        $q = DB::table('customers as c')
            ->leftJoin('customer_loyalty_profiles as p', 'p.customer_id', '=', 'c.id')
            ->leftJoinSub(
                DB::table('sales')
                    ->where('status', 'active')
                    ->selectRaw('customer_id, MAX(sale_date) as last_visit, COUNT(*) as visits, AVG(discount_amount) as avg_discount')
                    ->groupBy('customer_id'),
                'sa',
                'sa.customer_id', '=', 'c.id'
            )
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at');

        if ($branchId) $q->where('c.branch_id', $branchId);

        $today = Carbon::today();

        switch ($name) {
            case 'vip':
                $q->where('p.tier', 'vip');
                $q->orderByDesc('p.lifetime_spent');
                break;
            case 'inactive':
                $q->where(function ($qq) use ($today) {
                    $qq->where('sa.last_visit', '<', $today->copy()->subDays(self::INACTIVE_DAYS)->toDateString())
                       ->orWhereNull('sa.last_visit');
                });
                $q->orderBy('sa.last_visit');
                break;
            case 'churn_risk':
                $q->whereBetween('sa.last_visit', [
                    $today->copy()->subDays(self::INACTIVE_DAYS)->toDateString(),
                    $today->copy()->subDays(self::CHURN_RISK_DAYS)->toDateString(),
                ]);
                $q->where('p.lifetime_visits', '>=', 2);
                break;
            case 'discount_sensitive':
                $q->where('sa.avg_discount', '>', 0);
                $q->orderByDesc('sa.avg_discount');
                break;
            case 'high_frequency':
                $q->where('sa.visits', '>=', 5);
                $q->whereDate('sa.last_visit', '>=', $today->copy()->subDays(self::RECENT_DAYS)->toDateString());
                $q->orderByDesc('sa.visits');
                break;
            default:
                $q->orderByDesc('p.lifetime_spent');
        }

        return $q->select([
            'c.id', 'c.name', 'c.phone', 'c.email',
            'p.tier', 'p.lifetime_spent',
            'sa.last_visit', 'sa.visits', 'sa.avg_discount',
        ]);
    }

    private function countSegment(string $name, ?int $branchId): int
    {
        $q = $this->segmentBaseQuery($name, $branchId);
        return (int) $q->reorder()->count();
    }

    private function repeatCustomerPercent(?int $branchId): float
    {
        $row = DB::table('customers as c')
            ->leftJoinSub(
                DB::table('sales')->where('status', 'active')
                    ->selectRaw('customer_id, COUNT(*) as visits')
                    ->groupBy('customer_id'),
                'sa', 'sa.customer_id', '=', 'c.id'
            )
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->when($branchId, fn($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('
                COUNT(c.id) as total,
                SUM(CASE WHEN sa.visits >= 2 THEN 1 ELSE 0 END) as repeated
            ')
            ->first();

        if (!$row || $row->total <= 0) return 0.0;
        return round(((int) $row->repeated / (int) $row->total) * 100, 1);
    }

    private function avgLifetimeValue(?int $branchId): float
    {
        $row = DB::table('customers as c')
            ->leftJoin('customer_loyalty_profiles as p', 'p.customer_id', '=', 'c.id')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->when($branchId, fn($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('AVG(COALESCE(p.lifetime_spent, 0)) as avg_clv')
            ->first();
        return (float) ($row->avg_clv ?? 0);
    }

    private function topSpending(?int $branchId, int $limit): array
    {
        return DB::table('customers as c')
            ->join('customer_loyalty_profiles as p', 'p.customer_id', '=', 'c.id')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->when($branchId, fn($q) => $q->where('c.branch_id', $branchId))
            ->orderByDesc('p.lifetime_spent')
            ->limit($limit)
            ->get(['c.id', 'c.name', 'p.tier', 'p.lifetime_spent', 'p.lifetime_visits'])
            ->map(fn($r) => [
                'customer_id'     => (int) $r->id,
                'name'            => $r->name,
                'tier'            => $r->tier,
                'lifetime_spent'  => round((float) $r->lifetime_spent, 2),
                'lifetime_visits' => (int) $r->lifetime_visits,
            ])->all();
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') {
            return $branchId;
        }
        return Auth::user()?->branch_id;
    }
}
