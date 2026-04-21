<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Requests\StoreProductRequest;
use App\Modules\Product\Requests\UpdateProductRequest;
use App\Modules\Product\Resources\ProductResource;
use App\Modules\Product\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $this->service->paginate(
            $request->only('search', 'category_id', 'brand_id', 'is_active', 'per_page', 'page')
        );
        return ProductResource::collection($data);
    }

    public function all(): JsonResponse
    {
        $products = $this->service->all()->map(fn($p) => [
            'id'         => $p->id,
            'sku'        => $p->sku,
            'name'       => $p->name,
            'cost_price' => (float) $p->cost_price,
            'tax_rate'   => (float) $p->tax_rate,
            'unit_id'    => $p->unit_id,
            'image_url'  => $p->image ? Storage::url($p->image) : null,
        ]);

        return response()->json(['data' => $products]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();
        if (strlen($term) < 2) return response()->json([]);
        return response()->json($this->service->search($term));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'brand', 'unit']));
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        return new ProductResource($this->service->store($request->validated()));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->service->update($product, $request->validated()));
    }

    public function toggleStatus(Product $product): ProductResource
    {
        return new ProductResource($this->service->toggleStatus($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);
        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, Product $product): ProductResource
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $path = $request->file('image')->store('products', 'public');
        $product->update(['image' => $path]);

        return new ProductResource($product->fresh(['category', 'brand', 'unit']));
    }

    public function deleteImage(Product $product): JsonResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
            $product->update(['image' => null]);
        }

        return response()->json(null, 204);
    }
}
