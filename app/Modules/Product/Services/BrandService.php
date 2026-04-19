<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Brand::query()
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
        return Brand::active()->orderBy('name')->get(['id', 'name']);
    }

    public function store(array $data): Brand
    {
        return Brand::create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);
        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
