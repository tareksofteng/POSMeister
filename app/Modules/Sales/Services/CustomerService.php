<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Customer::query();

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(fn($s) =>
                $s->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('phone', 'like', $term)
            );
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('is_active', (bool) $filters['is_active']);
        }

        return $q->orderBy('name')->paginate($filters['per_page'] ?? 20);
    }

    public function all(): Collection
    {
        return Customer::active()->orderBy('name')->get(['id', 'code', 'name', 'phone', 'customer_type', 'credit_limit']);
    }

    public function store(array $data): Customer
    {
        $data['code'] = $this->generateCode();
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    private function generateCode(): string
    {
        $last = Customer::withTrashed()->max('id') ?? 0;
        return 'KUND-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
