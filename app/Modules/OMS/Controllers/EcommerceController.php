<?php

namespace App\Modules\OMS\Controllers;

use App\Modules\OMS\Ecommerce\EcommerceAdapterRegistry;
use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;
use App\Modules\OMS\Services\EcommerceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EcommerceController extends Controller
{
    public function __construct(
        private readonly EcommerceSyncService $sync,
        private readonly EcommerceAdapterRegistry $registry,
    ) {}

    public function connectors(): JsonResponse
    {
        return response()->json([
            'data' => EcommerceConnector::query()->orderBy('name')->get(),
            'registered_types' => $this->registry->types(),
        ]);
    }

    public function storeConnector(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'type'       => 'required|in:woocommerce,shopify,custom',
            'api_url'    => 'required|string|max:255',
            'api_key'    => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'settings'   => 'nullable|array',
            'is_active'  => 'boolean',
        ]);
        return response()->json(['data' => EcommerceConnector::create($data)], 201);
    }

    public function updateConnector(EcommerceConnector $connector, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:150',
            'api_url'    => 'sometimes|string|max:255',
            'api_key'    => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'settings'   => 'nullable|array',
            'is_active'  => 'boolean',
        ]);
        $connector->update($data);
        return response()->json(['data' => $connector->fresh()]);
    }

    public function destroyConnector(EcommerceConnector $connector): JsonResponse
    {
        $connector->delete();
        return response()->json(['data' => ['ok' => true]]);
    }

    public function startSync(EcommerceConnector $connector, Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity'    => 'required|in:products,stock,customers,orders',
            'direction' => 'nullable|in:pull,push,bidirectional',
        ]);
        $job = $this->sync->queue($connector, $data['entity'], $data['direction'] ?? 'pull');
        $job = $this->sync->run($job); // inline for now; queue worker in future
        return response()->json(['data' => $job], 201);
    }

    public function jobs(Request $request): JsonResponse
    {
        $q = SyncJob::query()->with('connector:id,name,type')->orderByDesc('id');
        if ($status      = $request->input('status'))       $q->where('status', $status);
        if ($entity      = $request->input('entity'))       $q->where('entity', $entity);
        if ($connectorId = $request->input('connector_id')) $q->where('connector_id', $connectorId);
        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }
}
