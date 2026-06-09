<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | CustomerDetector — Phase AB
 |--------------------------------------------------------------------------
 |
 | Watches the receivables side of the business and the VIP relationship
 | health. Four checks:
 |
 |   1. payment_due_today      : credit-sale invoices coming due in the
 |                               next 24h (collector queue).
 |   2. payment_overdue        : invoices with a due_date > 7 days old
 |                               where the balance hasn't been cleared.
 |   3. large_outstanding      : a single customer carries an outstanding
 |                               balance > 100k currency units AND >40%
 |                               of total receivables → concentration risk.
 |   4. vip_customer_inactive  : the customer's lifetime spend is in the
 |                               top decile, but their last sale was
 |                               > 60 days ago → churn warning.
 |
 | Sales table is the source-of-truth for receivables. CustomerPayments
 | reduce the outstanding. Inputs are branch-scoped so a manager sees
 | only their branch's customer risk.
 */
class CustomerDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        if (!Schema::hasTable('sales')) return 0;
        $pushed = 0;
        foreach ($this->activeBranchIds() as $branchId) {
            $pushed += $this->paymentDueToday($branchId);
            $pushed += $this->paymentOverdue($branchId);
            $pushed += $this->largeOutstanding($branchId);
            $pushed += $this->vipCustomerInactive($branchId);
        }
        return $pushed;
    }

    // ── Detectors ────────────────────────────────────────────────────────

    private function paymentDueToday(int $branchId): int
    {
        // `due_date` column may not exist on every install — we degrade
        // gracefully when it's absent.
        if (!Schema::hasColumn('sales', 'due_date')) return 0;

        $count = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereRaw('COALESCE(grand_total, 0) > COALESCE(total_paid, 0)')
            ->whereDate('due_date', today())
            ->count();

        if ($count === 0) return 0;

        $this->notify->push([
            'category'         => 'customer',
            'code'             => 'customer.payment_due_today',
            'severity'         => 'warning',
            'urgency'          => 65,
            'title'            => "{$count} customer payment(s) due today",
            'message'          => 'Send reminders before the day ends to recover cash on time.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "customer.payment_due_today:branch-{$branchId}:".today()->format('Y-m-d'),
            'cooldown_minutes' => 1440,
            'actions'          => [
                ['label' => 'menu.customerDue', 'route' => 'customer-due', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function paymentOverdue(int $branchId): int
    {
        $col = Schema::hasColumn('sales', 'due_date') ? 'due_date' : 'sale_date';

        $count = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereRaw('COALESCE(grand_total, 0) > COALESCE(total_paid, 0)')
            ->where($col, '<', now()->subDays(7)->toDateString())
            ->count();

        if ($count === 0) return 0;

        $sev = $count > 30 ? 'critical' : ($count > 10 ? 'danger' : 'warning');

        $this->notify->push([
            'category'         => 'customer',
            'code'             => 'customer.payment_overdue',
            'severity'         => $sev,
            'urgency'          => $sev === 'critical' ? 90 : 75,
            'title'            => "{$count} customer invoice(s) overdue 7+ days",
            'message'          => 'Concentrate collection activity on overdue customers first.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "customer.payment_overdue:branch-{$branchId}",
            'cooldown_minutes' => 720,
            'actions'          => [
                ['label' => 'menu.customerDue', 'route' => 'customer-due', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function largeOutstanding(int $branchId): int
    {
        // Concentration risk — a single customer's outstanding balance > 40%
        // of the branch's total receivables AND > 100k currency units.
        $perCustomer = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->whereRaw('COALESCE(grand_total, 0) > COALESCE(total_paid, 0)')
            ->selectRaw('customer_id, SUM(COALESCE(grand_total, 0) - COALESCE(total_paid, 0)) as due')
            ->groupBy('customer_id')
            ->get();

        $total = $perCustomer->sum('due');
        if ($total < 1) return 0;

        $offenders = $perCustomer->filter(fn ($r) => $r->due > 100_000 && ($r->due / $total) > 0.40);
        if ($offenders->isEmpty()) return 0;

        $names = DB::table('customers')
            ->whereIn('id', $offenders->pluck('customer_id'))
            ->pluck('name', 'id');

        $first = $offenders->first();
        $label = $names[$first->customer_id] ?? "ID #{$first->customer_id}";

        $this->notify->push([
            'category'         => 'customer',
            'code'             => 'customer.large_outstanding',
            'severity'         => 'danger',
            'urgency'          => 80,
            'title'            => "Customer concentration risk: {$label}",
            'message'          => 'A single customer holds > 40% of branch receivables — diversify or settle.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "customer.large_outstanding:branch-{$branchId}",
            'cooldown_minutes' => 1440,
            'actions'          => [
                ['label' => 'menu.customers', 'route' => 'customers', 'type' => 'primary'],
            ],
            'meta'             => [
                'count'     => $offenders->count(),
                'top_due'   => (float) $first->due,
                'share_pct' => round(($first->due / $total) * 100, 1),
                'branch_id' => $branchId,
            ],
        ]);
        return 1;
    }

    private function vipCustomerInactive(int $branchId): int
    {
        if (!Schema::hasTable('customers')) return 0;

        // Lifetime spend per customer — top 10% by total grand_total.
        $top = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(grand_total) as lifetime, MAX(sale_date) as last_sale')
            ->groupBy('customer_id')
            ->orderByDesc('lifetime')
            ->limit(20)        // top 20 is a practical proxy for "top decile"
            ->get();

        $stale = $top->filter(fn ($r) => $r->last_sale && $r->last_sale < now()->subDays(60)->toDateString());
        if ($stale->isEmpty()) return 0;

        $this->notify->push([
            'category'         => 'customer',
            'code'             => 'customer.vip_inactive',
            'severity'         => 'warning',
            'urgency'          => 55,
            'title'            => $stale->count() . " VIP customer(s) inactive 60+ days",
            'message'          => 'Re-engage your highest-value customers before they churn.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "customer.vip_inactive:branch-{$branchId}",
            'cooldown_minutes' => 4320,         // 3-day reminder cadence
            'actions'          => [
                ['label' => 'menu.customers', 'route' => 'customers'],
            ],
            'meta'             => [
                'count'     => $stale->count(),
                'branch_id' => $branchId,
            ],
        ]);
        return 1;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function activeBranchIds(): array
    {
        if (!Schema::hasTable('branches')) return [];
        return DB::table('branches')->where('is_active', true)->pluck('id')->all();
    }
}
