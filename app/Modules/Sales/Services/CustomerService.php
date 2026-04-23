<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\CustomerPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerService
{
    // ── List ──────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Customer::withSum(['sales as total_due_raw' => fn($s) => $s->where('status', 'active')], 'due_amount')
            ->withSum('payments as total_paid_due', 'amount');

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

    // ── Single ────────────────────────────────────────────────────────────

    public function find(int $id): Customer
    {
        return Customer::withSum(['sales as total_sales_amount' => fn($s) => $s->where('status', 'active')], 'grand_total')
            ->withCount(['sales as total_sales_count' => fn($s) => $s->where('status', 'active')])
            ->withSum(['sales as total_due_raw' => fn($s) => $s->where('status', 'active')], 'due_amount')
            ->withSum('payments as total_paid_due', 'amount')
            ->findOrFail($id);
    }

    // ── Ledger ────────────────────────────────────────────────────────────

    public function getRecentSales(Customer $customer, int $limit = 10): Collection
    {
        return $customer->sales()
            ->where('status', 'active')
            ->orderByDesc('sale_date')
            ->limit($limit)
            ->get(['id', 'sale_number', 'sale_date', 'grand_total', 'total_paid', 'due_amount']);
    }

    public function getPayments(Customer $customer): Collection
    {
        return $customer->payments()
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
    }

    public function storePayment(Customer $customer, array $data): CustomerPayment
    {
        return $customer->payments()->create([
            'branch_id'      => $data['branch_id'] ?? auth()->user()->branch_id,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payment_date'   => $data['payment_date'],
            'reference'      => $data['reference'] ?? null,
            'note'           => $data['note'] ?? null,
            'created_by'     => auth()->id(),
        ]);
    }

    // ── Create / Update ───────────────────────────────────────────────────

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

    // ── Helpers ───────────────────────────────────────────────────────────

    private function generateCode(): string
    {
        $last = Customer::withTrashed()->max('id') ?? 0;
        return 'KUND-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
