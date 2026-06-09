<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | SupplierDetector — Phase AB
 |--------------------------------------------------------------------------
 |
 | Supplier-relationship health watches. Three checks:
 |
 |   1. payment_due           : invoices coming due in the next 3 days
 |                              (queue reminders for payables clerk).
 |   2. payment_overdue       : invoices > 7 days past due — escalates
 |                              progressively to danger / critical based
 |                              on volume.
 |   3. supplier_inactivity   : a previously-active supplier (had ≥ 5
 |                              purchases in the past year) has been
 |                              silent for > 90 days. Often signals a
 |                              broken supply chain or an account dispute.
 |
 | All checks scope to the workspace branch so a Dhaka manager isn't
 | spammed by Chattogram's supplier health.
 */
class SupplierDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        if (!Schema::hasTable('purchases') || !Schema::hasTable('suppliers')) return 0;
        $pushed = 0;
        foreach ($this->activeBranchIds() as $branchId) {
            $pushed += $this->paymentDueSoon($branchId);
            $pushed += $this->paymentOverdue($branchId);
            $pushed += $this->supplierInactivity($branchId);
        }
        return $pushed;
    }

    // ── Detectors ────────────────────────────────────────────────────────

    private function paymentDueSoon(int $branchId): int
    {
        // due_date is the canonical column on purchases; fall back to
        // a +30 day offset on purchase_date when the column isn't
        // present (older installs).
        $hasDueDate = Schema::hasColumn('purchases', 'due_date');
        $paidCol    = Schema::hasColumn('purchases', 'paid_amount') ? 'paid_amount' : 'total_paid';

        $q = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->where('status', 'received')
            ->whereRaw("COALESCE(total_amount, 0) > COALESCE({$paidCol}, 0)");

        if ($hasDueDate) {
            $q->whereDate('due_date', '>=', today())
              ->whereDate('due_date', '<=', today()->addDays(3));
        } else {
            $q->whereDate('purchase_date', '>=', today()->subDays(30))
              ->whereDate('purchase_date', '<=', today()->subDays(27));
        }
        $count = $q->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'         => 'supplier',
            'code'             => 'supplier.payment_due',
            'severity'         => 'warning',
            'urgency'          => 60,
            'title'            => "{$count} supplier payment(s) due in 3 days",
            'message'          => 'Schedule payments before they slip into overdue status.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "supplier.payment_due:branch-{$branchId}",
            'cooldown_minutes' => 1440,
            'actions'          => [
                ['label' => 'menu.suppliers', 'route' => 'suppliers', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function paymentOverdue(int $branchId): int
    {
        $paidCol = Schema::hasColumn('purchases', 'paid_amount') ? 'paid_amount' : 'total_paid';

        $count = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->where('status', 'received')
            ->whereRaw("COALESCE(total_amount, 0) > COALESCE({$paidCol}, 0)")
            ->where('purchase_date', '<', now()->subDays(7)->toDateString())
            ->count();

        if ($count === 0) return 0;

        $sev = $count > 30 ? 'critical' : ($count > 10 ? 'danger' : 'warning');

        $this->notify->push([
            'category'         => 'supplier',
            'code'             => 'supplier.payment_overdue',
            'severity'         => $sev,
            'urgency'          => $sev === 'critical' ? 90 : 75,
            'title'            => "{$count} supplier invoice(s) overdue 7+ days",
            'message'          => 'Late payments damage trade-credit and may halt deliveries.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "supplier.payment_overdue:branch-{$branchId}",
            'cooldown_minutes' => 720,
            'actions'          => [
                ['label' => 'menu.suppliers', 'route' => 'suppliers', 'type' => 'primary'],
            ],
            'meta'             => ['count' => $count, 'branch_id' => $branchId],
        ]);
        return 1;
    }

    private function supplierInactivity(int $branchId): int
    {
        // "Previously active" = at least 5 purchases in the past 365 days.
        // Among those, find any with no purchases in the past 90 days.
        $inactive = DB::table('purchases')
            ->where('branch_id', $branchId)
            ->whereNotNull('supplier_id')
            ->where('purchase_date', '>=', now()->subDays(365)->toDateString())
            ->selectRaw('supplier_id, COUNT(*) as total_purchases, MAX(purchase_date) as last_purchase')
            ->groupBy('supplier_id')
            ->having('total_purchases', '>=', 5)
            ->get()
            ->filter(fn ($r) => $r->last_purchase < now()->subDays(90)->toDateString());

        if ($inactive->isEmpty()) return 0;

        $this->notify->push([
            'category'         => 'supplier',
            'code'             => 'supplier.inactivity',
            'severity'         => 'warning',
            'urgency'          => 50,
            'title'            => $inactive->count() . " regular supplier(s) inactive 90+ days",
            'message'          => 'Re-engage or replace inactive suppliers to keep the supply chain healthy.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "supplier.inactivity:branch-{$branchId}",
            'cooldown_minutes' => 10080,        // weekly cadence — slow signal
            'actions'          => [
                ['label' => 'menu.suppliers', 'route' => 'suppliers'],
            ],
            'meta'             => [
                'count'     => $inactive->count(),
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
