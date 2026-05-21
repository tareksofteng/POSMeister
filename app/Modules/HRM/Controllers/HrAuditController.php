<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\HrAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HrAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = HrAuditLog::query()->with('actor:id,name')->orderByDesc('id');

        if ($action     = $request->input('action'))      $q->where('action', $action);
        if ($entityType = $request->input('entity_type')) $q->where('entity_type', $entityType);
        if ($entityId   = $request->input('entity_id'))   $q->where('entity_id', $entityId);
        if ($actorId    = $request->input('actor_id'))    $q->where('actor_id', $actorId);
        if ($from       = $request->input('from'))        $q->whereDate('created_at', '>=', $from);
        if ($to         = $request->input('to'))          $q->whereDate('created_at', '<=', $to);

        return response()->json($q->paginate((int) $request->input('per_page', 50)));
    }
}
