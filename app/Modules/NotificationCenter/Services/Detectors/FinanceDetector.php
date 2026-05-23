<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        $pushed = 0;
        $pushed += $this->supplierDue();
        $pushed += $this->unpaidPurchases();
        return $pushed;
    }

    private function supplierDue(): int
    {
        if (!Schema::hasTable('purchases')) return 0;
        $count = DB::table('purchases')
            ->whereRaw('COALESCE(total_amount,0) > COALESCE(paid_amount,0)')
            ->whereDate('purchase_date', '<=', now())
            ->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'finance',
            'code'          => 'finance.supplier_due',
            'severity'      => $count > 10 ? 'warning' : 'info',
            'urgency'       => 50,
            'title'         => "{$count} supplier invoice(s) awaiting payment",
            'message'       => 'Review and settle outstanding supplier balances.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'finance.supplier_due',
            'cooldown_minutes' => 720,
            'actions'       => [['label' => 'menu.supplierDue', 'route' => 'supplier-due', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function unpaidPurchases(): int
    {
        if (!Schema::hasTable('purchases')) return 0;
        $count = DB::table('purchases')
            ->whereRaw('COALESCE(total_amount,0) > COALESCE(paid_amount,0)')
            ->where('purchase_date', '<', now()->subDays(45))
            ->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'finance',
            'code'          => 'finance.purchase_overdue',
            'severity'      => 'danger',
            'urgency'       => 75,
            'title'         => "{$count} purchase invoice(s) overdue 45+ days",
            'message'       => 'Risk of supplier escalation. Settle to maintain credit.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'finance.purchase_overdue',
            'cooldown_minutes' => 1440,
            'actions'       => [['label' => 'menu.supplierDue', 'route' => 'supplier-due', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }
}
