<?php

namespace App\Modules\Purchase\Controllers;

use App\Modules\Purchase\Resources\PurchaseReturnResource;
use App\Modules\Purchase\Services\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PurchaseReturnController extends Controller
{
    public function __construct(private readonly PurchaseReturnService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $returns = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'per_page',
        ]));

        return PurchaseReturnResource::collection($returns);
    }

    public function show(int $id): PurchaseReturnResource
    {
        return new PurchaseReturnResource($this->service->find($id));
    }

    public function record(Request $request): JsonResponse
    {
        $returns = $this->service->record($request->only('date_from', 'date_to', 'supplier_id'));

        return response()->json([
            'data'    => PurchaseReturnResource::collection($returns),
            'summary' => [
                'count'        => $returns->count(),
                'total_amount' => round($returns->sum('total_amount'), 2),
            ],
        ]);
    }

    public function returnDetails(int $purchaseId): JsonResponse
    {
        try {
            return response()->json($this->service->getReturnDetails($purchaseId));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function store(Request $request): PurchaseReturnResource
    {
        $request->validate([
            'purchase_id'             => 'required|integer|exists:purchases,id',
            'return_date'             => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.purchase_item_id'=> 'required|integer|exists:purchase_items,id',
            'items.*.product_id'      => 'required|integer|exists:products,id',
            'items.*.return_qty'      => 'required|numeric|min:0',
            'items.*.unit_cost'       => 'required|numeric|min:0',
            'note'                    => 'nullable|string|max:500',
        ]);

        try {
            $return = $this->service->store($request->all());
            return new PurchaseReturnResource($return);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
