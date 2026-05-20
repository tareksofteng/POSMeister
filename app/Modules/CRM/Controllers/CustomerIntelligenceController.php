<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Services\CustomerIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerIntelligenceController extends Controller
{
    public function __construct(private readonly CustomerIntelligenceService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        return response()->json(['data' => $this->service->dashboard($data['branch_id'] ?? null)]);
    }

    public function profile(int $customerId): JsonResponse
    {
        return response()->json(['data' => $this->service->timeline($customerId)]);
    }

    public function behavior(int $customerId): JsonResponse
    {
        return response()->json(['data' => $this->service->behavior($customerId)]);
    }

    public function segments(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id']);
        return response()->json(['data' => $this->service->segmentCounts($data['branch_id'] ?? null)]);
    }

    public function segmentList(string $name, Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'limit'     => 'nullable|integer|min:1|max:500',
        ]);
        $allowed = ['vip', 'inactive', 'churn_risk', 'discount_sensitive', 'high_frequency'];
        if (!in_array($name, $allowed, true)) {
            abort(404, 'Unknown segment.');
        }
        return response()->json([
            'data' => $this->service->segment($name, $data['branch_id'] ?? null, $data['limit'] ?? 100),
        ]);
    }
}
