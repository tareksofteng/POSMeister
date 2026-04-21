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
