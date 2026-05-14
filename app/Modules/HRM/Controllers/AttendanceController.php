<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function daily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'      => 'required|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json($this->service->dailySheet(
            $data['date'],
            $data['branch_id'] ?? null,
        ));
    }

    public function bulkMark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                 => 'required|date',
            'rows'                 => 'required|array|min:1',
            'rows.*.employee_id'   => 'required|integer|exists:employees,id',
            'rows.*.status'        => 'required|in:present,absent,leave,late,half_day',
            'rows.*.check_in'      => 'nullable',
            'rows.*.check_out'     => 'nullable',
            'rows.*.remarks'       => 'nullable|string|max:255',
        ]);

        return response()->json(
            $this->service->bulkMark($data['date'], $data['rows'])
        );
    }

    public function monthly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'          => 'required|integer|min:2000|max:2100',
            'month'         => 'required|integer|min:1|max:12',
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        return response()->json($this->service->monthlyMatrix(
            $data['year'],
            $data['month'],
            $data['branch_id']     ?? null,
            $data['department_id'] ?? null,
        ));
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->service->destroy($attendance);
        return response()->json(null, 204);
    }
}
