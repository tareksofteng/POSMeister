<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Shift;
use App\Modules\HRM\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ShiftResource::collection(
                Shift::orderBy('start_time')->get()
            ),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => Shift::active()
                ->orderBy('start_time')
                ->get(['id', 'name', 'start_time', 'end_time']),
        ]);
    }
}
