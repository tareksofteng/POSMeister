<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        if (!Schema::hasTable('sales')) return 0;
        $pushed = 0;
        $pushed += $this->salesDrop();
        $pushed += $this->overduePayments();
        $pushed += $this->noSalesActivity();
        return $pushed;
    }

    private function salesDrop(): int
    {
        $today  = (float) DB::table('sales')->whereDate('sale_date', today())->where('status', 'active')->sum('grand_total');
        $avg = (float) DB::table('sales')
            ->whereBetween('sale_date', [today()->subDays(8), today()->subDay()])
            ->where('status', 'active')
            ->sum('grand_total') / 7;

        if ($avg < 50 || $today >= $avg * 0.6) return 0;  // avoid noise on quiet days
        if (now()->hour < 14) return 0;                    // only after lunch

        $pct = round((1 - $today / $avg) * 100);
        $this->notify->push([
            'category'      => 'sales',
            'code'          => 'sales.drop',
            'severity'      => 'warning',
            'urgency'       => 60,
            'title'         => "Sales {$pct}% below 7-day average",
            'message'       => "Today: ".number_format($today, 2)." · 7d avg: ".number_format($avg, 2),
            'audience_role' => 'admin',
            'dedupe_key'    => 'sales.drop:'.today()->format('Y-m-d'),
            'cooldown_minutes' => 1440,
            'actions'       => [['label' => 'menu.dashboard', 'route' => 'dashboard']],
            'meta'          => ['today' => $today, 'avg' => $avg, 'pct' => $pct],
        ]);
        return 1;
    }

    private function overduePayments(): int
    {
        $count = DB::table('sales')
            ->where('status', 'active')
            ->whereRaw('COALESCE(grand_total,0) > COALESCE(total_paid,0)')
            ->where('sale_date', '<', now()->subDays(30))
            ->count();

        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'sales',
            'code'          => 'sales.overdue',
            'severity'      => $count > 20 ? 'danger' : 'warning',
            'urgency'       => 70,
            'title'         => "{$count} sales overdue more than 30 days",
            'message'       => 'Contact customers to recover outstanding balances.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'sales.overdue',
            'cooldown_minutes' => 720,
            'actions'       => [['label' => 'menu.customerDue', 'route' => 'customer-due', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function noSalesActivity(): int
    {
        $last = DB::table('sales')->where('status', 'active')->max('created_at');
        if (!$last) return 0;
        $hours = now()->diffInHours($last);
        if ($hours < 4) return 0;
        if (now()->hour < 11 || now()->hour > 21) return 0;   // only during business hours

        $this->notify->push([
            'category'      => 'sales',
            'code'          => 'sales.no_activity',
            'severity'      => 'warning',
            'urgency'       => 55,
            'title'         => "No sales activity for {$hours}h",
            'message'       => 'POS may not be in use. Verify operations.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'sales.no_activity',
            'cooldown_minutes' => 240,
            'actions'       => [['label' => 'menu.pointOfSale', 'route' => 'pos', 'type' => 'primary']],
            'meta'          => ['hours' => $hours],
        ]);
        return 1;
    }
}
