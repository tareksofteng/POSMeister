<?php

namespace App\Modules\Platform\Controllers;

use App\Modules\Platform\Models\SystemAuditLog;
use App\Modules\Platform\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    /** Public-ish liveness ping used by load balancers / uptime monitors. */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'service' => 'POSmeister',
            'at'      => now()->toIso8601String(),
        ]);
    }

    /** Detailed admin-only system snapshot. */
    public function health(): JsonResponse
    {
        return response()->json(['data' => $this->health->snapshot()]);
    }

    public function info(): JsonResponse
    {
        return response()->json(['data' => $this->health->version()]);
    }

    public function audit(Request $request): JsonResponse
    {
        $q = SystemAuditLog::query()->with('actor:id,name')->orderByDesc('id');
        if ($action   = $request->input('action'))   $q->where('action', $action);
        if ($severity = $request->input('severity')) $q->where('severity', $severity);
        if ($from     = $request->input('from'))     $q->whereDate('created_at', '>=', $from);
        if ($to       = $request->input('to'))       $q->whereDate('created_at', '<=', $to);
        return response()->json($q->paginate((int) $request->input('per_page', 50)));
    }
}
