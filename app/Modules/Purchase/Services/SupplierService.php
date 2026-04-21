<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Purchase\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Supplier::query();

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('is_active', (bool) $filters['is_active']);
        }

        return $q->orderBy('name')
                 ->paginate($filters['per_page'] ?? 20);
    }

    /** Lightweight list for dropdowns — only active suppliers. */
    public function all(): Collection
    {
        return Supplier::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'vat_number']);
    }

    public function store(array $data): Supplier
    {
        $data['code'] = $this->generateCode();
        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function toggleStatus(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => !$supplier->is_active]);
        return $supplier->fresh();
    }

    private function generateCode(): string
    {
        $last = Supplier::withTrashed()->orderByDesc('id')->value('code');
        if (!$last) return 'SUP-000001';
        $num = (int) substr($last, 4);
        return 'SUP-' . str_pad($num + 1, 6, '0', STR_PAD_LEFT);
    }
}
