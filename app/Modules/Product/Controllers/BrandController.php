<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Requests\StoreBrandRequest;
use App\Modules\Product\Requests\UpdateBrandRequest;
use App\Modules\Product\Resources\BrandResource;
use App\Modules\Product\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(private BrandService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $this->service->paginate($request->only('search', 'is_active', 'per_page', 'page'));
        return BrandResource::collection($data);
    }

    public function all(): JsonResponse
    {
        return response()->json($this->service->allActive());
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        return new BrandResource($this->service->store($request->validated()));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        return new BrandResource($this->service->update($brand, $request->validated()));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->service->delete($brand);
        return response()->json(null, 204);
    }
}
