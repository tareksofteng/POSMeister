<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\CustomerPayment;
use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    // ── Due Report ────────────────────────────────────────────────────────

    /**
     * Returns per-customer due breakdown:
     *   bill_amount        = sum of active sale grand totals
     *   invoice_paid       = sum of total_paid on those sales (paid at invoice time)
     *   cash_received      = sum of standalone customer payment records
     *   returned_amount    = sum of sale return total_amount for the customer
     *   due_amount         = bill_amount − invoice_paid − cash_received − returned_amount
     */
    public function dueReport(array $filters = []): Collection
    {
        $q = Customer::query()
            ->withSum(['sales as bill_amount' => fn($s) => $s->where('status', 'active')], 'grand_total')
            ->withSum(['sales as invoice_paid' => fn($s) => $s->where('status', 'active')], 'total_paid')
            ->withSum('payments as cash_received', 'amount')
            ->withSum('saleReturns as returned_amount', 'total_amount')
            ->orderBy('name');

        if (!empty($filters['customer_id'])) {
            $q->where('id', $filters['customer_id']);
        }

        return $q->get()->map(function (Customer $c) {
            $bill     = (float) ($c->bill_amount     ?? 0);
            $invPaid  = (float) ($c->invoice_paid    ?? 0);
            $cash     = (float) ($c->cash_received   ?? 0);
            $returned = (float) ($c->returned_amount ?? 0);
            $due      = max(0, $bill - $invPaid - $cash - $returned);

            return [
                'id'              => $c->id,
                'code'            => $c->code,
                'name'            => $c->name,
                'phone'           => $c->phone,
                'email'           => $c->email,
                'address'         => $c->address,
                'customer_type'   => $c->customer_type,
                'bill_amount'     => $bill,
                'invoice_paid'    => $invPaid,
                'cash_received'   => $cash,
                'returned_amount' => $returned,
                'total_paid'      => $invPaid + $cash + $returned,
                'due_amount'      => $due,
            ];
        });
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
