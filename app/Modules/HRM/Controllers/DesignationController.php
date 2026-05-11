<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Designation;
use App\Modules\HRM\Resources\DesignationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Designation::with('department:id,name');
        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }
        return response()->json([
            'data' => DesignationResource::collection($q->orderBy('title')->get()),
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $q = Designation::active();
        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }
        return response()->json([
            'data' => $q->orderBy('title')->get(['id', 'title', 'department_id', 'hierarchy_level']),
        ]);
    }
}
