<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Product::with(['category', 'brand', 'unit'])
            ->when(
                $filters['search'] ?? null,
                fn($q, $v) =>
                $q->where(
                    fn($sub) => $sub
                        ->where('name', 'like', "%{$v}%")
                        ->orWhere('sku', 'like', "%{$v}%")
                        ->orWhere('barcode', 'like', "%{$v}%")
                )
            )
            ->when($filters['category_id'] ?? null, fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['brand_id'] ?? null,    fn($q, $v) => $q->where('brand_id', $v))
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn($q) =>
                $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function all(): Collection
    {
        return Product::active()
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'cost_price', 'tax_rate', 'unit_id', 'image']);
    }

    public function search(string $term): Collection
    {
        return Product::with('unit')
            ->active()
            ->where(
                fn($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term)
            )
            ->limit(15)
            ->get(['id', 'sku', 'name', 'selling_price', 'tax_rate', 'unit_id']);
    }

    public function store(array $data): Product
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku();
        }

        if ($data['is_service'] ?? false) {
            $data['cost_price'] = 0;
        }

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if ($data['is_service'] ?? false) {
            $data['cost_price'] = 0;
        }

        $product->update($data);
        return $product->fresh(['category', 'brand', 'unit']);
    }

    public function toggleStatus(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function generateSku(): string
    {
        $next = (Product::withTrashed()->max('id') ?? 0) + 1;
        return 'P-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
