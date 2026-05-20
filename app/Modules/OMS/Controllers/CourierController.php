<?php

namespace App\Modules\OMS\Controllers;

use App\Modules\OMS\Couriers\CourierProviderRegistry;
use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;
use App\Modules\OMS\Models\Shipment;
use App\Modules\OMS\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CourierController extends Controller
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly CourierProviderRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Courier::query()->orderBy('name')->get(),
            'registered_codes' => $this->registry->codes(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:120',
            'code'              => 'required|string|max:32|unique:couriers,code',
            'api_endpoint'      => 'nullable|string|max:255',
            'api_key'           => 'nullable|string|max:255',
            'api_secret'        => 'nullable|string|max:255',
            'supported_regions' => 'nullable|array',
            'settings'          => 'nullable|array',
            'is_active'         => 'boolean',
        ]);
        return response()->json(['data' => Courier::create($data)], 201);
    }

    public function update(Courier $courier, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:120',
            'api_endpoint'      => 'nullable|string|max:255',
            'api_key'           => 'nullable|string|max:255',
            'api_secret'        => 'nullable|string|max:255',
            'supported_regions' => 'nullable|array',
            'settings'          => 'nullable|array',
            'is_active'         => 'boolean',
        ]);
        $courier->update($data);
        return response()->json(['data' => $courier->fresh()]);
    }

    public function destroy(Courier $courier): JsonResponse
    {
        $courier->delete();
        return response()->json(['data' => ['ok' => true]]);
    }

    public function ship(Order $order, Courier $courier): JsonResponse
    {
        return response()->json(['data' => $this->shipments->createForOrder($order, $courier)]);
    }

    public function shipments(Request $request): JsonResponse
    {
        $q = Shipment::query()
            ->with('order:id,order_number,customer_name', 'courier:id,name,code')
            ->orderByDesc('id');

        if ($status = $request->input('status')) $q->where('status', $status);
        if ($search = trim((string) $request->input('search', ''))) {
            $q->where('tracking_number', 'like', "%{$search}%");
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function refresh(Shipment $shipment): JsonResponse
    {
        return response()->json(['data' => $this->shipments->refreshStatus($shipment)]);
    }

    public function cancel(Shipment $shipment, Request $request): JsonResponse
    {
        $reason = $request->input('reason');
        return response()->json(['data' => $this->shipments->cancel($shipment, $reason)]);
    }
}
