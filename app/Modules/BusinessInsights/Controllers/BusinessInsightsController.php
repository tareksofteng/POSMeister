<?php

namespace App\Modules\BusinessInsights\Controllers;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\BusinessInsights\Models\BusinessInsight;
use App\Modules\BusinessInsights\Services\CustomerSegmentationService;
use App\Modules\BusinessInsights\Services\ForecastService;
use App\Modules\BusinessInsights\Services\InsightCaptureService;
use App\Modules\BusinessInsights\Services\InventoryIntelligenceService;
use App\Modules\BusinessInsights\Services\ProductOpportunityService;
use App\Modules\BusinessInsights\Services\SupplierIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BusinessInsightsController extends Controller
{
    /**
     * GET /api/insights/timeline?bucket=today|yesterday|week|month
     *
     * Workspace-scoped — admin in Main Branch sees the aggregate, branch
     * users see their own + global rows. Pinned rows appear first inside
     * each bucket.
     */
    public function timeline(Request $request): JsonResponse
    {
        $bucket = $request->input('bucket', 'today');
        $ctx    = app(BranchContextService::class);

        [$from, $to] = match ($bucket) {
            'yesterday' => [now()->subDay()->startOfDay(),   now()->subDay()->endOfDay()],
            'week'      => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'month'     => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            default     => [now()->startOfDay(),             now()->endOfDay()],
        };

        $q = BusinessInsight::query()
            ->where('observed_at', '>=', $from)
            ->where('observed_at', '<=', $to);

        $q = $this->scopeForBranch($q, $ctx);

        $rows = $q->orderByRaw("
                CASE status
                    WHEN 'pinned'   THEN 0
                    WHEN 'active'   THEN 1
                    WHEN 'resolved' THEN 2
                    WHEN 'ignored'  THEN 3
                END
            ")
            ->orderByDesc('observed_at')
            ->limit(80)
            ->get()
            ->map(fn (BusinessInsight $i) => $this->shape($i))
            ->all();

        return response()->json([
            'data'    => $rows,
            'counts'  => $this->counts($ctx),
            'bucket'  => $bucket,
        ]);
    }

    public function markStatus(Request $request, BusinessInsight $insight): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,resolved,ignored,pinned'],
        ]);

        $insight->status = $data['status'];
        if ($data['status'] === BusinessInsight::STATUS_RESOLVED) {
            $insight->resolved_at = now();
            $insight->resolved_by = $request->user()->id;
        }
        $insight->save();

        return response()->json(['data' => $this->shape($insight)]);
    }

    /**
     * GET /api/insights/forecast?metric=revenue|profit|cash_flow&horizon=7|30|90
     */
    public function forecast(Request $request, ForecastService $svc): JsonResponse
    {
        $metric  = $request->input('metric', 'revenue');
        $horizon = (int) $request->input('horizon', 7);
        return response()->json(['data' => $svc->forecast($metric, $horizon)]);
    }

    public function forecastSummary(ForecastService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->dashboardSummary()]);
    }

    /**
     * POST /api/insights/capture — admin-triggered manual refresh of the
     * timeline. Normally fires via the scheduler.
     */
    public function capture(InsightCaptureService $svc): JsonResponse
    {
        $captured = $svc->capture();
        return response()->json(['data' => ['captured' => $captured]]);
    }

    /**
     * GET /api/insights/customer-segments
     *   Returns the segment counts + totals + top-3-per-segment overview.
     *
     * GET /api/insights/customer-segments?segment=Platinum
     *   Returns the full list of customers in the requested segment.
     */
    public function customerSegments(Request $request, CustomerSegmentationService $svc): JsonResponse
    {
        $segment = $request->input('segment');
        if ($segment) {
            return response()->json([
                'data' => [
                    'segment'   => $segment,
                    'customers' => $svc->listForSegment($segment, 50),
                ],
            ]);
        }
        return response()->json(['data' => $svc->summary()]);
    }

    /**
     * GET /api/insights/inventory
     *   Everything an owner needs to manage stock in one shot: turnover
     *   ratio, aging buckets, the most-imminent stockouts, and the
     *   30-day velocity leaders.
     */
    public function inventoryIntelligence(InventoryIntelligenceService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->summary()]);
    }

    /**
     * GET /api/insights/suppliers
     *   Procurement-side companion to the customer/inventory views:
     *   spend leaders, concentration risk, lead times, inactivity,
     *   payment performance.
     */
    public function supplierIntelligence(SupplierIntelligenceService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->summary()]);
    }

    /**
     * GET /api/insights/opportunities
     *   Market-basket associations + category growth + margin mix —
     *   answers "what should I bundle / push / shelf next?".
     */
    public function productOpportunities(ProductOpportunityService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->summary()]);
    }

    // ── Internal ────────────────────────────────────────────────────────

    private function scopeForBranch($q, BranchContextService $ctx)
    {
        if ($ctx->isMainBranch()) return $q;
        $current = $ctx->current();
        if ($current === null) return $q;
        return $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $current));
    }

    private function counts(BranchContextService $ctx): array
    {
        $base = BusinessInsight::query();
        $base = $this->scopeForBranch($base, $ctx);

        return [
            'active'   => (clone $base)->where('status', 'active')->count(),
            'pinned'   => (clone $base)->where('status', 'pinned')->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->where('resolved_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function shape(BusinessInsight $i): array
    {
        return [
            'id'           => $i->id,
            'code'         => $i->code,
            'kind'         => $i->kind,
            'severity'     => $i->severity,
            'confidence'   => $i->confidence,
            'title'        => $i->title,
            'detail'       => $i->detail,
            'meta'         => $i->meta,
            'action'       => $i->action,
            'branch_id'    => $i->branch_id,
            'status'       => $i->status,
            'observed_at'  => optional($i->observed_at)->toIso8601String(),
            'resolved_at'  => optional($i->resolved_at)->toIso8601String(),
        ];
    }
}
