<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 |--------------------------------------------------------------------------
 | NotificationAnalyticsService — Phase AB
 |--------------------------------------------------------------------------
 |
 | Read-only aggregations over smart_notifications. Powers the
 | /api/notifications/analytics endpoint and the future "Notification
 | Health" dashboard widget.
 |
 | Every metric is window-bounded so the queries stay cheap even on
 | installations with hundreds of thousands of historical alerts. The
 | service is workspace-aware: a Dhaka manager sees only Dhaka analytics,
 | while a Main Branch / All Branches admin sees the aggregate.
 |
 | Metrics produced:
 |
 |   summary               unresolved / critical / last_24h / last_7d
 |   by_category           7-day count grouped by category
 |   by_priority           7-day count grouped by severity
 |   resolved_vs_unresolved 7-day acked vs untouched
 |   top_recurring         the alert codes the system is repeating most
 |                         often — your tuning candidates
 |   avg_resolution_time   from created_at to acked_at, in minutes
 |   branch_comparison     unresolved alerts per branch (admin only)
 |   timeline              30-day daily count trend (sparkline source)
 */
class NotificationAnalyticsService
{
    public function summary(): array
    {
        return [
            'summary'              => $this->headline(),
            'by_category'          => $this->byCategory(),
            'by_priority'          => $this->byPriority(),
            'resolved_vs_unresolved' => $this->resolvedSplit(),
            'top_recurring'        => $this->topRecurring(),
            'avg_resolution_time'  => $this->avgResolutionMinutes(),
            'branch_comparison'    => $this->branchComparison(),
            'timeline'             => $this->dailyTimeline(),
        ];
    }

    // ── Headline KPIs ────────────────────────────────────────────────────

    public function headline(): array
    {
        $base = $this->scoped();

        return [
            'unresolved' => (clone $base)
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->count(),
            'critical'   => (clone $base)
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->where('severity', 'critical')
                ->count(),
            'last_24h'   => (clone $base)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'last_7d'    => (clone $base)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
        ];
    }

    public function byCategory(): array
    {
        return $this->scoped()
            ->where('created_at', '>=', now()->subWeek())
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->all();
    }

    public function byPriority(): array
    {
        return $this->scoped()
            ->where('created_at', '>=', now()->subWeek())
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->all();
    }

    public function resolvedSplit(): array
    {
        $week = now()->subWeek();
        $base = $this->scoped()->where('created_at', '>=', $week);

        $resolved   = (clone $base)->whereNotNull('acked_at')->count();
        $unresolved = (clone $base)->whereNull('acked_at')->whereNull('archived_at')->count();

        return [
            'resolved'   => $resolved,
            'unresolved' => $unresolved,
            'pct_resolved' => ($resolved + $unresolved) > 0
                ? round(($resolved / ($resolved + $unresolved)) * 100, 1)
                : 0,
        ];
    }

    /**
     * Notification codes the system has been emitting most often in the
     * past week. The chattier the code, the better it pays to tune its
     * cooldown / threshold — these are the candidates the admin should
     * look at first when "the bell never stops ringing".
     */
    public function topRecurring(int $limit = 10): array
    {
        return $this->scoped()
            ->where('created_at', '>=', now()->subWeek())
            ->selectRaw('code, COUNT(*) as occurrences, MAX(severity) as last_severity, MAX(created_at) as last_seen')
            ->groupBy('code')
            ->orderByDesc('occurrences')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'code'          => $r->code,
                'occurrences'   => (int) $r->occurrences,
                'last_severity' => $r->last_severity,
                'last_seen'     => $r->last_seen,
            ])
            ->all();
    }

    /**
     * Average time from "created_at" to "acked_at" in minutes — measures
     * how quickly the team responds to actionable alerts. NULL when no
     * alerts have been acked in the window.
     */
    public function avgResolutionMinutes(): ?float
    {
        $row = $this->scoped()
            ->whereNotNull('acked_at')
            ->where('created_at', '>=', now()->subWeek())
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, acked_at)) as avg_minutes')
            ->first();

        return $row && $row->avg_minutes !== null
            ? round((float) $row->avg_minutes, 1)
            : null;
    }

    /**
     * Per-branch unresolved counts — admin uses this to spot a branch
     * that's running hot relative to its peers. Only meaningful when
     * the user is in Main Branch / All Branches; per-branch workspaces
     * already only see their own data so this collapses to one row.
     */
    public function branchComparison(): array
    {
        return SmartNotification::query()
            ->whereNull('acked_at')
            ->whereNull('archived_at')
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as unresolved')
            ->groupBy('branch_id')
            ->orderByDesc('unresolved')
            ->get()
            ->map(fn ($r) => [
                'branch_id'  => (int) $r->branch_id,
                'unresolved' => (int) $r->unresolved,
            ])
            ->all();
    }

    /**
     * 30-day daily alert volume — feeds the sparkline / trend chart so
     * the admin sees at a glance whether the system has been quieter
     * (= business healthier) or noisier (= more risks surfacing).
     */
    public function dailyTimeline(int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();
        $rows = $this->scoped()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->all();

        // Fill in the zeroes so the frontend gets a contiguous series.
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'count' => (int) ($rows[$d] ?? 0)];
        }
        return $out;
    }

    // ── Scoping helper ───────────────────────────────────────────────────

    /**
     * Workspace-aware base query. Mirrors the rule used by the
     * NotificationCenterController: in Main Branch / All Branches we
     * see everything (admin); in a specific branch we see that branch
     * + global (null branch_id) alerts.
     */
    private function scoped()
    {
        $q = SmartNotification::query();

        $ctx = app(BranchContextService::class);
        if ($ctx->isMainBranch()) return $q;

        $current = $ctx->current();
        if ($current === null) return $q;

        return $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $current));
    }
}
