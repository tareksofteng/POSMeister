<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Department;
use App\Modules\HRM\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => DepartmentResource::collection(
                Department::orderBy('name')->get()
            ),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => Department::active()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }
}
