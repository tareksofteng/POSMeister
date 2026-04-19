<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ProductCategory::query()
            ->when($filters['search'] ?? null, fn($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
            )
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn($q) =>
                $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function allActive(): Collection
    {
        return ProductCategory::active()->orderBy('name')->get(['id', 'name']);
    }

    public function store(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }
}
