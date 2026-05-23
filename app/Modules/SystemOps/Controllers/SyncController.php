<?php

namespace App\Modules\SystemOps\Controllers;

use App\Modules\SystemOps\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SyncController extends Controller
{
    public function __construct(private readonly OfflineSyncService $sync) {}

    public function pending(): JsonResponse
    {
        return response()->json(['data' => [
            'summary' => $this->sync->summary(),
            'recent'  => $this->sync->recent(50),
        ]]);
    }

    public function prune(): JsonResponse
    {
        $deleted = $this->sync->prune(keepHours: 168);
        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
