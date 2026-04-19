<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Requests\StoreCategoryRequest;
use App\Modules\Product\Requests\UpdateCategoryRequest;
use App\Modules\Product\Resources\CategoryResource;
use App\Modules\Product\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $this->service->paginate($request->only('search', 'is_active', 'per_page', 'page'));
        return CategoryResource::collection($data);
    }

    public function all(): JsonResponse
    {
        return response()->json($this->service->allActive());
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        return new CategoryResource($this->service->store($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, ProductCategory $category): CategoryResource
    {
        return new CategoryResource($this->service->update($category, $request->validated()));
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $this->service->delete($category);
        return response()->json(null, 204);
    }
}
