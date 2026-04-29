<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Purchase\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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

    public function dueReport(array $filters = []): Collection
    {
        $q = Supplier::query()
            ->withSum(
                ['purchases as bill_amount' => fn($s) => $s->where('status', 'received')],
                'total_amount'
            )
            ->withSum('payments as cash_paid', 'amount')
            ->withSum('purchaseReturns as returned_amount', 'total_amount')
            ->orderBy('name');

        if (!empty($filters['supplier_id'])) {
            $q->where('id', $filters['supplier_id']);
        }

        return $q->get()->map(function (Supplier $s) {
            $bill     = (float) ($s->bill_amount     ?? 0);
            $cashPaid = (float) ($s->cash_paid       ?? 0);
            $returned = (float) ($s->returned_amount ?? 0);
            $due      = max(0, $bill - $cashPaid - $returned);

            return [
                'id'             => $s->id,
                'code'           => $s->code,
                'name'           => $s->name,
                'contact_person' => $s->contact_person,
                'phone'          => $s->phone,
                'address'        => $s->address,
                'bill_amount'    => $bill,
                'cash_paid'      => $cashPaid,
                'returned_amount'=> $returned,
                'total_paid'     => $cashPaid + $returned,
                'due_amount'     => $due,
            ];
        });
    }

    private function generateCode(): string
    {
        $last = Supplier::withTrashed()->orderByDesc('id')->value('code');
        if (!$last) return 'SUP-000001';
        $num = (int) substr($last, 4);
        return 'SUP-' . str_pad($num + 1, 6, '0', STR_PAD_LEFT);
    }
}
