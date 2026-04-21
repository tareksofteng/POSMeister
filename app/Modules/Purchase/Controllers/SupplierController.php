<?php

namespace App\Modules\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Requests\StoreSupplierRequest;
use App\Modules\Purchase\Resources\SupplierResource;
use App\Modules\Purchase\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            $this->service->paginate($request->only('search', 'is_active', 'per_page'))
        );
    }

    public function all(): JsonResponse
    {
        return response()->json(['data' => $this->service->all()]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->service->store($request->validated());
        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        return new SupplierResource($this->service->update($supplier, $request->validated()));
    }

    public function toggleStatus(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($this->service->toggleStatus($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        try {
            $this->service->delete($supplier);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }
}
