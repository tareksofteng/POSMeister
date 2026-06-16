<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * RFM customer segmentation. Each customer who bought in the past 365
 * days gets a (Recency, Frequency, Monetary) score on a 1–5 scale; the
 * combination maps to one of five tiers the business owner intuitively
 * recognises:
 *
 *   Platinum   — recent + frequent + high spend; the relationship goldmine
 *   Gold       — broadly healthy across all three axes
 *   Silver     — middling on at least two axes
 *   Bronze     — qualifies on one axis only
 *   Dormant    — recency tail (last purchase ≥180 days ago)
 *
 * Workspace-scoped via BranchContextService. Cached 30 minutes per
 * branch tuple — RFM doesn't shift fast enough to warrant a hot path.
 *
 * No external data, no AI, no LLM. Pure SQL + thresholds you can audit.
 */
class CustomerSegmentationService
{
    public const SEGMENTS = ['Platinum', 'Gold', 'Silver', 'Bronze', 'Dormant'];

    public function summary(): array
    {
        $branchId = $this->resolveBranchId();
        $cacheKey = "customer.segments:summary:" . ($branchId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($branchId) {
            $rows = $this->computeScores($branchId);

            $counts = array_fill_keys(self::SEGMENTS, 0);
            foreach ($rows as $r) {
                $counts[$r['segment']]++;
            }

            $totals = [
                'customers'  => count($rows),
                'revenue'    => round(array_sum(array_column($rows, 'monetary_value')), 2),
                'avg_basket' => $rows ? round(array_sum(array_column($rows, 'monetary_value')) / max(1, array_sum(array_column($rows, 'frequency_count'))), 2) : 0,
            ];

            // Top 3 per segment — the shop floor uses these to pick up the
            // phone, not to admire a chart.
            $top = [];
            foreach (self::SEGMENTS as $seg) {
                $top[$seg] = collect($rows)
                    ->where('segment', $seg)
                    ->sortByDesc('monetary_value')
                    ->take(3)
                    ->values()
                    ->all();
            }

            return [
                'counts'  => $counts,
                'totals'  => $totals,
                'top'     => $top,
                'as_of'   => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Listing endpoint — every customer in a given segment with their
     * RFM scores. Used by the customer-segments view drill-down.
     */
    public function listForSegment(string $segment, int $limit = 50): array
    {
        if (!in_array($segment, self::SEGMENTS, true)) $segment = 'Platinum';
        $branchId = $this->resolveBranchId();

        $rows = $this->computeScores($branchId);
        return collect($rows)
            ->where('segment', $segment)
            ->sortByDesc('monetary_value')
            ->take($limit)
            ->values()
            ->all();
    }

    // ── Scoring ──────────────────────────────────────────────────────────

    private function computeScores(?int $branchId): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasTable('customers')) return [];

        $cutoff = now()->subDays(365)->toDateString();

        // Single aggregation per customer — recency, frequency, monetary.
        $rows = DB::table('customers as c')
            ->join('sales as s', 's.customer_id', '=', 'c.id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $cutoff)
            ->whereNull('c.deleted_at')
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('
                c.id, c.name, c.code, c.phone,
                MAX(s.sale_date) as last_sale,
                COUNT(s.id)      as freq,
                SUM(s.grand_total) as monetary
            ')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.phone')
            ->get();

        if ($rows->isEmpty()) return [];

        // Monetary quintile cutoffs — use percentiles instead of fixed
        // thresholds so the model fits the actual customer base.
        $values = $rows->pluck('monetary')->map(fn ($v) => (float) $v)->sort()->values()->all();
        $count = count($values);
        $q = fn(float $p) => $values[(int) min($count - 1, floor($count * $p))] ?? 0;
        $mCuts = [
            'q1' => $q(0.20),
            'q2' => $q(0.40),
            'q3' => $q(0.60),
            'q4' => $q(0.80),
        ];

        $today = Carbon::today();
        $out = [];
        foreach ($rows as $r) {
            $daysSince = $r->last_sale ? $today->diffInDays(Carbon::parse($r->last_sale)) : 999;
            $freq      = (int) $r->freq;
            $monetary  = (float) $r->monetary;

            $rScore = match (true) {
                $daysSince <  30  => 5,
                $daysSince <  60  => 4,
                $daysSince <  90  => 3,
                $daysSince < 180  => 2,
                default           => 1,
            };
            $fScore = match (true) {
                $freq >= 10 => 5,
                $freq >=  5 => 4,
                $freq >=  3 => 3,
                $freq >=  1 => 2,
                default     => 1,
            };
            $mScore = match (true) {
                $monetary > $mCuts['q4'] => 5,
                $monetary > $mCuts['q3'] => 4,
                $monetary > $mCuts['q2'] => 3,
                $monetary > $mCuts['q1'] => 2,
                default                   => 1,
            };

            $segment = $this->mapSegment($rScore, $fScore, $mScore, $daysSince);

            $out[] = [
                'customer_id'      => (int) $r->id,
                'name'             => $r->name,
                'code'             => $r->code,
                'phone'            => $r->phone,
                'last_sale'        => $r->last_sale,
                'days_since'       => $daysSince,
                'frequency_count'  => $freq,
                'monetary_value'   => round($monetary, 2),
                'r_score'          => $rScore,
                'f_score'          => $fScore,
                'm_score'          => $mScore,
                'rfm_total'        => $rScore + $fScore + $mScore,
                'segment'          => $segment,
            ];
        }

        return $out;
    }

    /**
     * Tier mapping — pure rule-based so any owner can audit "why am I
     * Platinum?" without diving into ML model weights.
     *
     *   Dormant   — last purchase ≥180 days ago, regardless of past spend
     *   Platinum  — top of every axis (R≥4, F≥4, M≥4)
     *   Gold      — broadly healthy (R≥3, F≥3, M≥3) and not Platinum
     *   Silver    — passes 2 of 3 at score ≥3
     *   Bronze    — everyone else who bought in the past year
     */
    private function mapSegment(int $r, int $f, int $m, int $daysSince): string
    {
        if ($daysSince >= 180) return 'Dormant';
        if ($r >= 4 && $f >= 4 && $m >= 4) return 'Platinum';
        if ($r >= 3 && $f >= 3 && $m >= 3) return 'Gold';

        $pass = (int) ($r >= 3) + (int) ($f >= 3) + (int) ($m >= 3);
        if ($pass >= 2) return 'Silver';
        return 'Bronze';
    }

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
