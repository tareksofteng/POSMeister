<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | PurchaseDetector — Phase AB
 |--------------------------------------------------------------------------
 |
 | Surfaces procurement-side risks before they bite. Four checks:
 |
 |   1. supplier_payment_overdue : a posted purchase invoice older than 30
 |                                 days still carries an unpaid balance.
 |                                 We dedupe by branch_id so a Dhaka
 |                                 manager sees only Dhaka overdues.
 |   2. pending_approval         : purchases sitting in draft for > 48h
 |                                 (often blocks goods receipt).
 |   3. missing_goods_receipt    : the order was placed > 14 days ago and
 |                                 status is still draft (= no GRN).
 |   4. abnormal_cost_increase   : the same product was bought today at a
 |                                 unit cost > 25% higher than its
 |                                 30-day rolling average → tooling /
 |                                 supplier-pricing change worth a flag.
 |
 | Every notification is branch-scoped (audience_role=admin still applies)
 | so a multi-branch business can route alerts to the right managers.
 |
 | Cooldowns are intentionally long — these are slow-moving signals; we
 | don't want the bell icon screaming every 10 minutes.
 */
class PurchaseDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        if (!Schema::hasTable('purchases')) return 0;
        $pushed = 0;
        foreach ($this->activeBranchIds() as $branchId) {
            $pushed += $this->supplierPaymentOverdue($branchId);
            $pushed += $this->pendingApproval($branchId);
            $pushed += $this->missingGoodsReceipt($branchId);
            $pushed += $this->abnormalCostIncrease($branchId);
        }
        return $pushed;
    }

    // ── Detectors ────────────────────────────────────────────────────────

    private function supplierPaymentOverdue(int $branchId): int
    {
        // total_amount and paid_amount columns are stable across the
        // schema; we degrade gracefully if either column is renamed in a
        // future migration.
        if (!Schema::hasColumn('purchases', 'total_amount')) return 0;
        $paidCol = Schema::hasColumn('purchases', 'paid_amount') ? 'paid_amount' : 'total_paid';

        $count = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->where('status', 'received')
            ->whereRaw("COALESCE(total_amount, 0) > COALESCE({$paidCol}, 0)")
            ->where('purchase_date', '<', now()->subDays(30)->toDateString())
            ->count();

        if ($count === 0) return 0;

        $this->notify->push([
            'category'         => 'purchase',
            'code'             => 'purchase.supplier_payment_overdue',
            'severity'         => $count > 10 ? 'danger' : 'warning',
            'urgency'          => 70,
            'title'            => "{$count} supplier invoice(s) overdue 30+ days",
            'message'          => 'Settle outstanding supplier balances to protect credit lines.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "purchase.supplier_payment_overdue:branch-{$branchId}",
            'cooldown_minutes' => 720,
            'actions'          => [
                ['label' => 'menu.supplierDue', 'route' => 'supplier-due', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function pendingApproval(int $branchId): int
    {
        $count = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->where('status', 'draft')
            ->where('created_at', '<', now()->subHours(48))
            ->count();

        if ($count === 0) return 0;

        $this->notify->push([
            'category'         => 'purchase',
            'code'             => 'purchase.pending_approval',
            'severity'         => 'warning',
            'urgency'          => 55,
            'title'            => "{$count} purchase order(s) pending > 48h",
            'message'          => 'Approve or cancel stale draft purchase orders.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "purchase.pending_approval:branch-{$branchId}",
            'cooldown_minutes' => 480,
            'actions'          => [
                ['label' => 'menu.purchases', 'route' => 'purchases', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function missingGoodsReceipt(int $branchId): int
    {
        // 14-day window — typical lead time for non-imported goods is 7–10
        // days, so anything still in draft past 14 days has likely been
        // forgotten, not just delayed.
        $count = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->where('status', 'draft')
            ->where('purchase_date', '<', now()->subDays(14)->toDateString())
            ->count();

        if ($count === 0) return 0;

        $this->notify->push([
            'category'         => 'purchase',
            'code'             => 'purchase.missing_goods_receipt',
            'severity'         => 'warning',
            'urgency'          => 60,
            'title'            => "{$count} purchase(s) missing goods receipt 14+ days",
            'message'          => 'Receive goods or cancel the order to free up the supplier credit.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "purchase.missing_goods_receipt:branch-{$branchId}",
            'cooldown_minutes' => 720,
            'actions'          => [
                ['label' => 'menu.purchases', 'route' => 'purchases'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function abnormalCostIncrease(int $branchId): int
    {
        // Compare today's unit cost against the 30-day average for the
        // same product / branch combination. A >25% jump on the same
        // supplier flags a pricing surprise worth investigating.
        if (!Schema::hasTable('purchase_items')) return 0;

        $rows = DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->where('p.branch_id', $branchId)
            ->whereDate('p.purchase_date', today())
            ->selectRaw('
                pi.product_id,
                pi.unit_cost as today_cost,
                (
                    SELECT AVG(pi2.unit_cost)
                    FROM purchase_items pi2
                    JOIN purchases p2 ON p2.id = pi2.purchase_id
                    WHERE pi2.product_id = pi.product_id
                      AND p2.branch_id  = p.branch_id
                      AND p2.purchase_date >= ?
                      AND p2.purchase_date <  ?
                ) as baseline
            ', [now()->subDays(30)->toDateString(), today()->toDateString()])
            ->get();

        $hits = 0;
        foreach ($rows as $r) {
            if (!$r->baseline || $r->baseline <= 0) continue;
            if ($r->today_cost > $r->baseline * 1.25) $hits++;
        }
        if ($hits === 0) return 0;

        $this->notify->push([
            'category'         => 'purchase',
            'code'             => 'purchase.abnormal_cost_increase',
            'severity'         => 'warning',
            'urgency'          => 60,
            'title'            => "{$hits} product(s) bought today >25% above 30d avg",
            'message'          => 'Verify supplier pricing or new tax / freight charges.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "purchase.abnormal_cost_increase:branch-{$branchId}:".today()->format('Y-m-d'),
            'cooldown_minutes' => 1440,
            'actions'          => [
                ['label' => 'menu.purchaseRecord', 'route' => 'purchase-record'],
            ],
            'meta'             => ['hits' => $hits, 'branch_id' => $branchId],
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
