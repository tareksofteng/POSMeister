<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\BusinessInsights\Models\BusinessInsight;
use App\Modules\Dashboard\Services\BusinessHealthService;
use App\Modules\Dashboard\Services\DashboardInsightsService;

/*
 * Bridges the existing rule-based detectors (DashboardInsightsService +
 * BusinessHealthService) into the persistent insight timeline. Runs on a
 * schedule so the timeline view always shows the latest snapshot for
 * Today / Yesterday / Last 7d / Last 30d.
 *
 * Dedupe is by (code, period_key) — the same code firing twice on the
 * same day UPSERTs the row instead of stacking, so the timeline stays
 * tidy. Severity changes from previous samples propagate; status the
 * user set ('resolved' / 'ignored' / 'pinned') never gets overwritten.
 */
class InsightCaptureService
{
    public function __construct(
        private readonly DashboardInsightsService $insights,
        private readonly BusinessHealthService    $health,
    ) {}

    /**
     * One pass — pulls fresh signals, persists each as a row in
     * business_insights. Returns the count captured.
     */
    public function capture(?int $branchId = null): int
    {
        $captured = 0;
        $periodKey = now()->toDateString();

        foreach ($this->insights->compute(20) as $payload) {
            $captured += $this->upsert(
                code:        $payload['kind'] . '.' . substr(md5($payload['title']), 0, 8),
                kind:        $payload['kind'] ?? 'system',
                severity:    $payload['severity'] ?? 'info',
                confidence:  70,
                title:       $payload['title'] ?? '—',
                detail:      $payload['detail'] ?? null,
                meta:        $payload['meta']   ?? null,
                action:      $payload['action'] ?? null,
                branchId:    $branchId,
                periodKey:   $periodKey,
            );
        }

        // Also stamp the health-score deltas so the timeline carries the
        // headline trajectory alongside the rule-based insights.
        $h = $this->health->compute();
        if (!empty($h['subscores']['sales'])) {
            $s = $h['subscores']['sales'];
            $captured += $this->upsert(
                code:       'sales.trajectory',
                kind:       'sales',
                severity:   $this->severityFromDelta($s['delta_pct'] ?? null),
                confidence: 80,
                title:      $s['note'] ?? 'Sales trajectory tracked.',
                detail:     null,
                meta:       ['delta_pct' => $s['delta_pct'] ?? null, 'score' => $s['score'] ?? null],
                action:     null,
                branchId:   $branchId,
                periodKey:  $periodKey,
            );
        }

        return $captured;
    }

    private function upsert(
        string $code,
        string $kind,
        string $severity,
        int $confidence,
        string $title,
        ?string $detail,
        ?array $meta,
        ?array $action,
        ?int $branchId,
        string $periodKey,
    ): int {
        $existing = BusinessInsight::query()
            ->where('code', $code)
            ->where('period_key', $periodKey)
            ->where('branch_id', $branchId)
            ->first();

        if ($existing) {
            // Only refresh the volatile fields — never overwrite user
            // intent (resolved / ignored / pinned).
            $existing->severity    = $severity;
            $existing->confidence  = $confidence;
            $existing->title       = $title;
            $existing->detail      = $detail;
            $existing->meta        = $meta;
            $existing->action      = $action;
            $existing->observed_at = now();
            $existing->save();
            return 0;
        }

        BusinessInsight::query()->create([
            'code'         => $code,
            'period_key'   => $periodKey,
            'kind'         => $kind,
            'severity'     => $severity,
            'confidence'   => $confidence,
            'title'        => $title,
            'detail'       => $detail,
            'meta'         => $meta,
            'action'       => $action,
            'branch_id'    => $branchId,
            'status'       => BusinessInsight::STATUS_ACTIVE,
            'observed_at'  => now(),
        ]);
        return 1;
    }

    private function severityFromDelta(?float $delta): string
    {
        if ($delta === null)  return 'info';
        if ($delta <= -20)    return 'danger';
        if ($delta <= -5)     return 'warning';
        if ($delta >=  10)    return 'positive';
        return 'info';
    }
}
