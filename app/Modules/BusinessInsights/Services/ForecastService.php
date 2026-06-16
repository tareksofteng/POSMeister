<?php

namespace App\Modules\BusinessInsights\Services;

use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Deterministic, no-AI forecasting. The shop floor cares about three
 * things on a given Monday morning: am I going to make my sales target
 * this week, do I have enough cash to pay suppliers, am I about to
 * stock out. This service answers those with a simple moving-average
 * projection so the answers are explainable and verifiable.
 *
 * Method per metric:
 *
 *   1. Pull the last `baselineDays` days of daily values from the same
 *      tables the rest of the dashboard reads (sales / sale_items /
 *      cashbook journal). Workspace-scoped via BranchContextService.
 *   2. Compute a centered weighted moving average — the most recent
 *      14 days carry twice the weight of the prior 14, because store-
 *      level patterns drift faster than they cycle.
 *   3. Project forward `horizonDays` days. Confidence is derived from
 *      the variance of the baseline series relative to its mean
 *      (coefficient of variation) and gets clipped into [40, 95].
 *
 * Cached for 30 minutes per (metric, horizon, branchScope) tuple so the
 * dashboard widget can poll cheaply.
 */
class ForecastService
{
    public const HORIZONS  = [7, 30, 90];
    public const METRICS   = ['revenue', 'profit', 'cash_flow'];
    private const BASELINE = 90;

    public function forecast(string $metric, int $horizon): array
    {
        if (!in_array($metric, self::METRICS, true))   $metric  = 'revenue';
        if (!in_array($horizon, self::HORIZONS, true)) $horizon = 7;

        $branchId = $this->resolveBranchId();
        $cacheKey = sprintf('forecast:%s:%d:%s', $metric, $horizon, $branchId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($metric, $horizon, $branchId) {
            $series = $this->baseline($metric, $branchId, self::BASELINE);
            if (count($series) < 14) {
                return $this->emptyResult($metric, $horizon, 'Not enough history yet — need at least 14 days of activity.');
            }

            [$recent, $prior] = $this->splitHalves($series);
            $recentAvg = $this->mean($recent);
            $priorAvg  = $this->mean($prior);

            // Weighted average — recent twice the prior. Forecast is flat
            // across the horizon (intentional; we're not modelling trend
            // explicitly here, the dashboard renders the line as the
            // weighted recent mean).
            $expectedDaily = $recentAvg * 0.67 + $priorAvg * 0.33;
            $forecastTotal = round($expectedDaily * $horizon, 2);

            // Confidence: coefficient of variation → [40, 95].
            $confidence = $this->confidence($series);

            // Trend delta — recent half vs prior half.
            $deltaPct = $priorAvg > 0 ? round((($recentAvg - $priorAvg) / $priorAvg) * 100, 1) : null;

            // Projected daily points for the chart.
            $points = [];
            for ($i = 1; $i <= $horizon; $i++) {
                $points[] = [
                    'date'  => now()->copy()->addDays($i)->toDateString(),
                    'value' => round($expectedDaily, 2),
                ];
            }

            return [
                'metric'           => $metric,
                'horizon_days'     => $horizon,
                'baseline_days'    => self::BASELINE,
                'expected_daily'   => round($expectedDaily, 2),
                'forecast_total'   => $forecastTotal,
                'trend_delta_pct'  => $deltaPct,
                'confidence'       => $confidence,
                'baseline_points'  => $this->normalisePoints($series),
                'forecast_points'  => $points,
                'note'             => $this->note($metric, $horizon, $forecastTotal, $deltaPct, $confidence),
            ];
        });
    }

    /**
     * Quick small payload for the dashboard widget — all three metrics
     * at the default 7-day horizon. Cheap because of the per-tuple cache.
     */
    public function dashboardSummary(): array
    {
        return [
            'revenue'   => $this->forecast('revenue',   7),
            'profit'    => $this->forecast('profit',    7),
            'cash_flow' => $this->forecast('cash_flow', 7),
        ];
    }

    // ── Baseline series builders (mirror DashboardTrendsService) ────────

    private function baseline(string $metric, ?int $branchId, int $days): array
    {
        $from = Carbon::today()->subDays($days);

        $raw = match ($metric) {
            'revenue'   => $this->revenueDaily($branchId, $from),
            'profit'    => $this->profitDaily($branchId, $from),
            'cash_flow' => $this->cashFlowDaily($branchId, $from),
        };

        // Zero-fill so a quiet Sunday doesn't shift the centre of mass.
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = (float) ($raw[$d] ?? 0);
        }
        return $out;
    }

    private function revenueDaily(?int $branchId, Carbon $from): array
    {
        if (!Schema::hasTable('sales')) return [];
        return DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(sale_date) as day, COALESCE(SUM(grand_total), 0) as v')
            ->groupBy('day')
            ->pluck('v', 'day')
            ->all();
    }

    private function profitDaily(?int $branchId, Carbon $from): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasTable('sale_items')) return [];

        $rev = $this->revenueDaily($branchId, $from);
        if (!$rev) return [];

        $cogs = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('DATE(s.sale_date) as day, COALESCE(SUM(si.quantity * si.cost_price), 0) as v')
            ->groupBy('day')
            ->pluck('v', 'day')
            ->all();

        $out = [];
        foreach ($rev as $day => $r) {
            $out[$day] = (float) $r - (float) ($cogs[$day] ?? 0);
        }
        return $out;
    }

    private function cashFlowDaily(?int $branchId, Carbon $from): array
    {
        $in  = $this->sumDaily($branchId, $from, 'customer_payments', 'amount', 'payment_date');
        $o1  = $this->sumDaily($branchId, $from, 'supplier_payments', 'amount', 'payment_date');
        $o2  = $this->sumDaily($branchId, $from, 'expenses',          'amount', 'expense_date', ['approved','paid']);
        $o3  = $this->sumDaily($branchId, $from, 'payslips',          'net_salary', 'payment_date', ['paid']);

        $days = array_unique(array_merge(array_keys($in), array_keys($o1), array_keys($o2), array_keys($o3)));
        $out = [];
        foreach ($days as $d) {
            $out[$d] = (float) ($in[$d] ?? 0)
                     - (float) ($o1[$d] ?? 0)
                     - (float) ($o2[$d] ?? 0)
                     - (float) ($o3[$d] ?? 0);
        }
        return $out;
    }

    private function sumDaily(?int $branchId, Carbon $from, string $table, string $col, string $dateCol, ?array $statusIn = null): array
    {
        if (!Schema::hasTable($table)) return [];
        $q = DB::table($table)
            ->whereDate($dateCol, '>=', $from->toDateString())
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));
        if ($statusIn) $q->whereIn('status', $statusIn);

        return $q->selectRaw("DATE({$dateCol}) as day, COALESCE(SUM({$col}), 0) as v")
                 ->groupBy('day')
                 ->pluck('v', 'day')
                 ->all();
    }

    // ── Math helpers ────────────────────────────────────────────────────

    private function splitHalves(array $series): array
    {
        $mid = intdiv(count($series), 2);
        return [array_slice($series, $mid), array_slice($series, 0, $mid)];
    }

    private function mean(array $arr): float
    {
        if (!$arr) return 0;
        return array_sum($arr) / count($arr);
    }

    private function confidence(array $series): int
    {
        $mean = $this->mean($series);
        if ($mean <= 0) return 50;
        $n = count($series);
        $variance = 0.0;
        foreach ($series as $v) $variance += ($v - $mean) ** 2;
        $variance = $variance / $n;
        $cv = sqrt($variance) / $mean;       // coefficient of variation
        // cv=0 → 95% confidence; cv≥1 → 40%. Smooth mapping.
        $score = (int) round(95 - min(55, $cv * 55));
        return max(40, min(95, $score));
    }

    private function normalisePoints(array $series): array
    {
        $from = Carbon::today()->subDays(count($series));
        $out = [];
        foreach ($series as $i => $v) {
            $out[] = ['date' => $from->copy()->addDays($i + 1)->toDateString(), 'value' => round($v, 2)];
        }
        return $out;
    }

    private function note(string $metric, int $horizon, float $total, ?float $deltaPct, int $confidence): string
    {
        $label = ['revenue' => 'Revenue', 'profit' => 'Profit', 'cash_flow' => 'Net cash flow'][$metric] ?? $metric;
        $trend = $deltaPct === null
            ? ''
            : ($deltaPct >= 0
                ? sprintf(' (+%.1f%% vs prior period)', $deltaPct)
                : sprintf(' (%.1f%% vs prior period)', $deltaPct));

        return sprintf(
            '%s over the next %d days: %s%s · confidence %d%%.',
            $label, $horizon, number_format($total), $trend, $confidence,
        );
    }

    private function emptyResult(string $metric, int $horizon, string $reason): array
    {
        return [
            'metric'           => $metric,
            'horizon_days'     => $horizon,
            'baseline_days'    => self::BASELINE,
            'expected_daily'   => 0,
            'forecast_total'   => 0,
            'trend_delta_pct'  => null,
            'confidence'       => 0,
            'baseline_points'  => [],
            'forecast_points'  => [],
            'note'             => $reason,
        ];
    }

    private function resolveBranchId(): ?int
    {
        $ctx = app(BranchContextService::class);
        return $ctx->isMainBranch() ? null : $ctx->current();
    }
}
