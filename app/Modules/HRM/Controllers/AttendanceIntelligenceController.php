<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Services\AttendanceIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceIntelligenceController extends Controller
{
    public function __construct(private readonly AttendanceIntelligenceService $service) {}

    public function scores(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'limit'     => 'nullable|integer|min:1|max:500',
        ]);
        return response()->json([
            'data' => $this->service->scores(
                $data['branch_id'] ?? null, $data['from'], $data['to'], $data['limit'] ?? 100,
            ),
        ]);
    }

    public function lateHeatmap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->lateHeatmap($data['branch_id'] ?? null, $data['from'], $data['to']),
        ]);
    }

    public function overtimeTrend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->overtimeTrend($data['branch_id'] ?? null, $data['from'], $data['to']),
        ]);
    }

    public function breaks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->service->breakAnalytics($data['branch_id'] ?? null, $data['from'], $data['to']),
        ]);
    }

    public function correct(Attendance $attendance, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'         => 'nullable|in:present,absent,leave,late,half_day',
            'check_in'       => 'nullable',
            'check_out'      => 'nullable',
            'worked_minutes' => 'nullable|integer|min:0|max:1440',
            'is_late'        => 'nullable|boolean',
            'remarks'        => 'nullable|string|max:500',
            'reason'         => 'required|string|max:255',
        ]);
        $reason = $data['reason'];
        unset($data['reason']);

        return response()->json([
            'data' => $this->service->correct($attendance, $data, $reason),
        ]);
    }
}
