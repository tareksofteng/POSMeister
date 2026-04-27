<?php

namespace App\Modules\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Requests\StorePurchaseRequest;
use App\Modules\Purchase\Resources\PurchaseResource;
use App\Modules\Purchase\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PurchaseResource::collection(
            $this->service->paginate(
                $request->only('search', 'status', 'supplier_id', 'branch_id', 'date_from', 'date_to', 'per_page')
            )
        );
    }

    public function record(Request $request): JsonResponse
    {
        $purchases = $this->service->record(
            $request->only('date_from', 'date_to', 'supplier_id', 'status')
        );

        return response()->json([
            'data'    => PurchaseResource::collection($purchases),
            'summary' => [
                'count'           => $purchases->count(),
                'subtotal'        => round($purchases->sum('subtotal'), 2),
                'vat_amount'      => round($purchases->sum('vat_amount'), 2),
                'discount_amount' => round($purchases->sum('discount_amount'), 2),
                'freight_amount'  => round($purchases->sum('freight_amount'), 2),
                'total_amount'    => round($purchases->sum('total_amount'), 2),
            ],
        ]);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($this->service->find($purchase->id));
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->service->store($request->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function update(StorePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        try {
            $updated = $this->service->update($purchase, $request->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return (new PurchaseResource($updated))->response();
    }

    public function receive(Purchase $purchase): JsonResponse
    {
        try {
            $updated = $this->service->receive($purchase);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return (new PurchaseResource($updated))->response();
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            $this->service->delete($purchase);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }
}
