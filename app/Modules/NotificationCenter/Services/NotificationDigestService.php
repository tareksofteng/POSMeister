<?php

namespace App\Modules\NotificationCenter\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\NotificationDigest;
use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a per-user daily digest summarising the previous 24 hours.
 * Idempotent — re-running for the same date upserts.
 */
class NotificationDigestService
{
    public function buildDaily(): int
    {
        $built = 0;
        $today = today();
        $since = now()->subDay();

        User::query()->where('is_active', true)->chunk(50, function ($users) use (&$built, $today, $since) {
            foreach ($users as $user) {
                $prefs = NotificationPreference::query()->where('user_id', $user->id)->first();
                if ($prefs && !($prefs->digest['daily'] ?? true)) continue;

                $summary = $this->snapshotFor($user, $since);
                NotificationDigest::query()->updateOrCreate(
                    ['user_id' => $user->id, 'period' => 'daily', 'for_date' => $today],
                    ['summary' => $summary]
                );
                $built++;
            }
        });

        return $built;
    }

    private function snapshotFor(User $user, $since): array
    {
        $role = $user->role ?? null;

        $alerts = SmartNotification::query()
            ->forUser($user->id, $role)
            ->where('created_at', '>=', $since)
            ->orderByDesc('urgency')
            ->limit(20)
            ->get(['code', 'category', 'severity', 'title', 'created_at']);

        $counts = [
            'total'    => $alerts->count(),
            'critical' => $alerts->where('severity', 'critical')->count(),
            'danger'   => $alerts->where('severity', 'danger')->count(),
            'warning'  => $alerts->where('severity', 'warning')->count(),
            'info'     => $alerts->where('severity', 'info')->count(),
        ];

        $business = [
            'sales_today'      => $this->salesToday(),
            'sales_yesterday'  => $this->salesYesterday(),
            'low_stock_count'  => $this->lowStockCount(),
            'overdue_count'    => $this->overdueCount(),
        ];

        return [
            'generated_at'  => now()->toIso8601String(),
            'role'          => $role,
            'counts'        => $counts,
            'top_alerts'    => $alerts->map(fn($a) => [
                'code'     => $a->code,
                'category' => $a->category,
                'severity' => $a->severity,
                'title'    => $a->title,
            ])->all(),
            'business'      => $business,
        ];
    }

    private function salesToday(): float
    {
        if (!Schema::hasTable('sales')) return 0;
        return (float) DB::table('sales')
            ->whereDate('sale_date', today())
            ->where('status', 'active')
            ->sum('grand_total');
    }

    private function salesYesterday(): float
    {
        if (!Schema::hasTable('sales')) return 0;
        return (float) DB::table('sales')
            ->whereDate('sale_date', today()->subDay())
            ->where('status', 'active')
            ->sum('grand_total');
    }

    private function lowStockCount(): int
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('products')) return 0;
        return (int) DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('products.is_active', true)
            ->distinct()
            ->count('products.id');
    }

    private function overdueCount(): int
    {
        if (!Schema::hasTable('sales')) return 0;
        return (int) DB::table('sales')
            ->where('status', 'active')
            ->whereRaw('COALESCE(grand_total,0) > COALESCE(total_paid,0)')
            ->where('sale_date', '<', now()->subDays(30))
            ->count();
    }
}
