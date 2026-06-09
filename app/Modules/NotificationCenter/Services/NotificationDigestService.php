<?php

namespace App\Modules\NotificationCenter\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\NotificationDigest;
use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | NotificationDigestService — Phase AB Round 4
 |--------------------------------------------------------------------------
 |
 | Three digest variants. Each has its own content shape because they
 | answer different questions:
 |
 |   MORNING  (cron 07:30)  — "What needs my attention today?"
 |     forward-looking: due today + low stock + open critical/danger
 |     alerts, plus a one-line yesterday-vs-today sales delta.
 |
 |   EVENING  (cron 18:00)  — "How did today go?"
 |     backward-looking: today's sales + alerts raised today + alerts
 |     resolved today + any criticals still open at end-of-day.
 |
 |   WEEKLY   (cron Monday 07:30) — "What pattern is the week showing?"
 |     7-day rollup: trend chart data, top recurring codes, resolution
 |     rate, branch-comparison row when there's more than one branch.
 |
 | Branch-grouping:
 |   When the user is admin (= sees every branch), each digest carries
 |   an extra `by_branch` block so the per-branch breakdown is visible
 |   without leaving the digest.
 |
 | Preview mode:
 |   `preview($user, $period)` computes the digest WITHOUT persisting.
 |   Used by the "Preview now" button in NotificationDigestView so the
 |   user can see what the next scheduled digest would look like at
 |   this very moment, with zero side-effects.
 |
 | Idempotency:
 |   Persisted digests are uniquely keyed by (user_id, period, for_date).
 |   Re-running the cron for the same date overwrites the existing row.
 */
class NotificationDigestService
{
    public const PERIOD_MORNING = 'morning';
    public const PERIOD_EVENING = 'evening';
    public const PERIOD_WEEKLY  = 'weekly';

    /**
     * Build + persist digests for every active user. Returns count.
     */
    public function build(string $period): int
    {
        $period = $this->normalizePeriod($period);
        $built = 0;
        $today = today();

        User::query()->where('is_active', true)->chunk(50, function ($users) use (&$built, $today, $period) {
            foreach ($users as $user) {
                $prefs = NotificationPreference::query()->where('user_id', $user->id)->first();
                if (!$this->wantsDigest($prefs, $period)) continue;

                $summary = $this->snapshotFor($user, $period);
                NotificationDigest::query()->updateOrCreate(
                    ['user_id' => $user->id, 'period' => $period, 'for_date' => $today],
                    ['summary' => $summary]
                );
                $built++;
            }
        });

        return $built;
    }

    /**
     * Backwards-compat shim. Existing schedule still calls buildDaily()
     * and we don't want to introduce an action-at-a-distance bug while
     * the new schedule rolls out. Maps to "morning" because that's the
     * variant whose content is closest to the old daily digest.
     */
    public function buildDaily(): int
    {
        return $this->build(self::PERIOD_MORNING);
    }

    /**
     * Live preview — same content shape as the persisted digest but
     * computed on demand, never written. Returned to the frontend so
     * the user can see exactly what their next digest will contain.
     */
    public function preview(User $user, string $period): array
    {
        return $this->snapshotFor($user, $this->normalizePeriod($period));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Period-aware content composer
    // ─────────────────────────────────────────────────────────────────────

    private function snapshotFor(User $user, string $period): array
    {
        $role = $user->role ?? null;
        $isAdmin = $role === 'admin';

        $window = $this->window($period);

        $alerts = SmartNotification::query()
            ->forUser($user->id, $role)
            ->where('created_at', '>=', $window['since'])
            ->orderByDesc('urgency')
            ->limit(20)
            ->get(['id', 'code', 'category', 'severity', 'title', 'branch_id', 'created_at', 'acked_at', 'archived_at']);

        $counts = [
            'total'    => $alerts->count(),
            'critical' => $alerts->where('severity', 'critical')->count(),
            'danger'   => $alerts->where('severity', 'danger')->count(),
            'warning'  => $alerts->where('severity', 'warning')->count(),
            'info'     => $alerts->where('severity', 'info')->count(),
        ];

        $payload = [
            'generated_at'  => now()->toIso8601String(),
            'period'        => $period,
            'window'        => [
                'since' => $window['since']->toIso8601String(),
                'until' => $window['until']->toIso8601String(),
                'label' => $window['label'],
            ],
            'role'          => $role,
            'counts'        => $counts,
            'top_alerts'    => $alerts->map(fn ($a) => [
                'id'         => $a->id,
                'code'       => $a->code,
                'category'   => $a->category,
                'severity'   => $a->severity,
                'title'      => $a->title,
                'branch_id'  => $a->branch_id,
                'acked'      => (bool) $a->acked_at,
                'archived'   => (bool) $a->archived_at,
            ])->all(),
        ];

        // Period-specific blocks ───────────────────────────────────────
        match ($period) {
            self::PERIOD_MORNING => $this->fillMorning($payload),
            self::PERIOD_EVENING => $this->fillEvening($payload, $user, $window),
            self::PERIOD_WEEKLY  => $this->fillWeekly($payload, $user, $window, $isAdmin),
        };

        // Admin gets a branch breakdown on every period — helps multi-branch
        // owners route attention without leaving the digest.
        if ($isAdmin) {
            $payload['by_branch'] = $this->branchBreakdown($window['since']);
        }

        return $payload;
    }

    /**
     * MORNING — forward-looking. The user wants to know what to act on
     * before lunch: due today, low stock, open critical alerts, and a
     * one-line sales delta to set the mood.
     */
    private function fillMorning(array &$payload): void
    {
        $payload['focus'] = [
            'heading'         => 'Today',
            'open_critical'   => SmartNotification::query()
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->where('severity', 'critical')
                ->count(),
            'low_stock_count' => $this->lowStockCount(),
            'due_today_count' => $this->dueTodayCount(),
        ];
        $payload['business'] = [
            'sales_today'     => $this->salesToday(),
            'sales_yesterday' => $this->salesYesterday(),
            'overdue_count'   => $this->overdueCount(),
        ];
    }

    /**
     * EVENING — backward-looking. Today's revenue + resolution rate +
     * outstanding criticals. Pairs nicely with the closing-of-day
     * ritual a shop manager performs.
     */
    private function fillEvening(array &$payload, User $user, array $window): void
    {
        $role = $user->role ?? null;
        $raisedToday = SmartNotification::query()
            ->forUser($user->id, $role)
            ->whereDate('created_at', today())
            ->count();
        $resolvedToday = SmartNotification::query()
            ->forUser($user->id, $role)
            ->whereNotNull('acked_at')
            ->whereDate('acked_at', today())
            ->count();

        $payload['focus'] = [
            'heading'           => 'How today went',
            'raised_today'      => $raisedToday,
            'resolved_today'    => $resolvedToday,
            'resolution_pct'    => $raisedToday > 0
                ? round(($resolvedToday / $raisedToday) * 100, 1)
                : 0,
            'open_critical_eod' => SmartNotification::query()
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->where('severity', 'critical')
                ->count(),
        ];
        $payload['business'] = [
            'sales_today'      => $this->salesToday(),
            'sales_yesterday'  => $this->salesYesterday(),
            'transactions_today' => $this->salesCountToday(),
        ];
    }

    /**
     * WEEKLY — 7-day rollup. This is the digest you read on Monday
     * morning over coffee: trend chart + top recurring codes + resolution
     * rate. Branch comparison rendered when there's > 1 branch.
     */
    private function fillWeekly(array &$payload, User $user, array $window, bool $isAdmin): void
    {
        $role = $user->role ?? null;
        $weekAgo = now()->subDays(7);

        $raised = SmartNotification::query()
            ->forUser($user->id, $role)
            ->where('created_at', '>=', $weekAgo)
            ->count();

        $resolved = SmartNotification::query()
            ->forUser($user->id, $role)
            ->whereNotNull('acked_at')
            ->where('acked_at', '>=', $weekAgo)
            ->count();

        $topRecurring = SmartNotification::query()
            ->forUser($user->id, $role)
            ->where('created_at', '>=', $weekAgo)
            ->selectRaw('code, COUNT(*) as occurrences, MAX(severity) as last_severity')
            ->groupBy('code')
            ->orderByDesc('occurrences')
            ->limit(5)
            ->get()
            ->all();

        // 7-day daily trend — zero-filled so the frontend chart renders a
        // contiguous series even on quiet days.
        $trendRaw = SmartNotification::query()
            ->forUser($user->id, $role)
            ->where('created_at', '>=', $weekAgo->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day')
            ->all();

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $trend[] = ['date' => $day, 'count' => (int) ($trendRaw[$day] ?? 0)];
        }

        $payload['focus'] = [
            'heading'        => 'Last 7 days',
            'raised'         => $raised,
            'resolved'       => $resolved,
            'resolution_pct' => $raised > 0 ? round(($resolved / $raised) * 100, 1) : 0,
            'avg_per_day'    => $raised > 0 ? round($raised / 7, 1) : 0,
        ];
        $payload['trend'] = $trend;
        $payload['top_recurring'] = $topRecurring;
        $payload['business'] = [
            'sales_week'  => $this->salesLastWeek(),
            'sales_prior' => $this->salesPriorWeek(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Branch breakdown for admin digests — count of unresolved alerts
     * per branch. Empty array when there's no branch-scoped data.
     */
    private function branchBreakdown(\Illuminate\Support\Carbon $since): array
    {
        return SmartNotification::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as total, SUM(CASE WHEN acked_at IS NULL AND archived_at IS NULL THEN 1 ELSE 0 END) as open')
            ->groupBy('branch_id')
            ->orderByDesc('open')
            ->get()
            ->map(fn ($r) => [
                'branch_id' => (int) $r->branch_id,
                'total'     => (int) $r->total,
                'open'      => (int) $r->open,
            ])
            ->all();
    }

    private function window(string $period): array
    {
        return match ($period) {
            self::PERIOD_MORNING => [
                'since' => now()->subDay(),
                'until' => now(),
                'label' => 'Past 24 hours',
            ],
            self::PERIOD_EVENING => [
                'since' => today()->startOfDay(),
                'until' => now(),
                'label' => 'Today so far',
            ],
            self::PERIOD_WEEKLY => [
                'since' => now()->subDays(7),
                'until' => now(),
                'label' => 'Past 7 days',
            ],
        };
    }

    /**
     * User opt-in check. The preferences schema stores opt-ins in a JSON
     * column under digest.{period}. Default behaviour for missing prefs:
     *   morning → true (most users want it)
     *   evening → false (opt-in, EOD email replacement)
     *   weekly  → true (low-volume, useful overview)
     */
    private function wantsDigest(?NotificationPreference $prefs, string $period): bool
    {
        $defaults = [
            self::PERIOD_MORNING => true,
            self::PERIOD_EVENING => false,
            self::PERIOD_WEEKLY  => true,
        ];
        if (!$prefs) return $defaults[$period] ?? true;
        $digest = $prefs->digest ?? [];

        // Legacy: the old schema had digest.daily = true|false. Treat it
        // as the morning preference when no morning-specific value exists.
        if ($period === self::PERIOD_MORNING) {
            return $digest['morning'] ?? $digest['daily'] ?? true;
        }
        return $digest[$period] ?? ($defaults[$period] ?? true);
    }

    private function normalizePeriod(string $period): string
    {
        // Soft alias — old 'daily' callers map to morning so the
        // backwards-compat path keeps working through one release.
        if ($period === 'daily') return self::PERIOD_MORNING;

        if (!in_array($period, [self::PERIOD_MORNING, self::PERIOD_EVENING, self::PERIOD_WEEKLY], true)) {
            return self::PERIOD_MORNING;
        }
        return $period;
    }

    // ── Business KPI helpers ─────────────────────────────────────────────

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

    private function salesCountToday(): int
    {
        if (!Schema::hasTable('sales')) return 0;
        return (int) DB::table('sales')
            ->whereDate('sale_date', today())
            ->where('status', 'active')
            ->count();
    }

    private function salesLastWeek(): float
    {
        if (!Schema::hasTable('sales')) return 0;
        return (float) DB::table('sales')
            ->whereDate('sale_date', '>=', today()->subDays(7))
            ->where('status', 'active')
            ->sum('grand_total');
    }

    private function salesPriorWeek(): float
    {
        if (!Schema::hasTable('sales')) return 0;
        return (float) DB::table('sales')
            ->whereDate('sale_date', '>=', today()->subDays(14))
            ->whereDate('sale_date', '<',  today()->subDays(7))
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

    private function dueTodayCount(): int
    {
        if (!Schema::hasTable('sales')) return 0;
        if (!Schema::hasColumn('sales', 'due_date')) {
            // Fall back to the canonical "overdue 30+ days" signal when
            // the install doesn't carry a due_date column.
            return $this->overdueCount();
        }
        return (int) DB::table('sales')
            ->where('status', 'active')
            ->whereRaw('COALESCE(grand_total,0) > COALESCE(total_paid,0)')
            ->whereDate('due_date', today())
            ->count();
    }
}
