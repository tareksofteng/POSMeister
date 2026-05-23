<?php

namespace App\Modules\SystemOps\Controllers;

use App\Modules\SystemOps\Services\OfflineSnapshotService;
use App\Modules\SystemOps\Services\SaleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfflineSyncController extends Controller
{
    public function __construct(
        private readonly OfflineSnapshotService $snapshot,
        private readonly SaleSyncService        $sales,
    ) {}

    /** GET /api/system/snapshot — bulk download for offline operation. */
    public function snapshot(Request $request): JsonResponse
    {
        $branchId = $request->user()?->branch_id ?? null;
        return response()->json(['data' => $this->snapshot->build($branchId)]);
    }

    /** POST /api/system/sync/sales — batch import of offline sales. */
    public function syncSales(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:80'],
            'sales'     => ['required', 'array', 'min:1', 'max:200'],
            'sales.*.idempotency_key'   => ['required', 'string', 'max:80'],
            'sales.*.offline_reference' => ['nullable', 'string', 'max:64'],
            'sales.*.data'              => ['required', 'array'],
        ]);

        $result = $this->sales->importBatch(
            deviceId: $data['device_id'],
            userId:   $request->user()?->id,
            rows:     $data['sales'],
        );

        return response()->json($result);
    }

    /** GET /api/system/devices — admin: list known offline terminals. */
    public function devices(): JsonResponse
    {
        if (!Schema::hasTable('device_sessions')) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('device_sessions')
            ->leftJoin('users', 'users.id', '=', 'device_sessions.user_id')
            ->orderByDesc('device_sessions.last_seen_at')
            ->limit(100)
            ->get([
                'device_sessions.id',
                'device_sessions.device_id',
                'device_sessions.user_id',
                'users.name as user_name',
                'device_sessions.label',
                'device_sessions.user_agent',
                'device_sessions.last_ip',
                'device_sessions.first_seen_at',
                'device_sessions.last_seen_at',
            ]);
        return response()->json(['data' => $rows]);
    }

    /** GET /api/system/sync/conflicts — admin: open sync conflicts. */
    public function conflicts(Request $request): JsonResponse
    {
        if (!Schema::hasTable('sync_conflicts')) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('sync_conflicts')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        return response()->json(['data' => $rows]);
    }
}
