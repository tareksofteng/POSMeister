<?php

namespace App\Modules\Reports\Services;

use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\CustomerPayment;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturn;
use App\Modules\Sales\Models\SaleReturnItem;

class LedgerService
{
    public function customer(int $customerId, ?string $from, ?string $to): array
    {
        $customer = Customer::findOrFail($customerId);
        $opening  = $this->customerOpening($customerId, $from);

        $sales = Sale::where('customer_id', $customerId)
            ->where('status', 'active')
            ->when($from, fn($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('sale_date', '<=', $to))
            ->orderBy('sale_date')->orderBy('id')
            ->get(['id', 'sale_number', 'sale_date', 'grand_total', 'note']);

        $payments = CustomerPayment::where('customer_id', $customerId)
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date')->orderBy('id')
            ->get(['id', 'amount', 'payment_date', 'payment_method', 'reference', 'note']);

        $returns = SaleReturn::where('customer_id', $customerId)
            ->when($from, fn($q) => $q->whereDate('return_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('return_date', '<=', $to))
            ->orderBy('return_date')->orderBy('id')
            ->get(['id', 'return_number', 'return_date', 'total_amount', 'note']);

        $rows = collect();

        foreach ($sales as $s) {
            $rows->push([
                'date'      => $s->sale_date->format('Y-m-d'),
                'type'      => 'sale',
                'reference' => $s->sale_number,
                'note'      => $s->note,
                'method'    => null,
                'debit'     => (float) $s->grand_total,
                'credit'    => 0.0,
            ]);
        }

        foreach ($payments as $p) {
            $rows->push([
                'date'      => $p->payment_date->format('Y-m-d'),
                'type'      => 'payment',
                'reference' => $p->reference,
                'note'      => $p->note,
                'method'    => $p->payment_method,
                'debit'     => 0.0,
                'credit'    => (float) $p->amount,
            ]);
        }

        foreach ($returns as $r) {
            $rows->push([
                'date'      => $r->return_date->format('Y-m-d'),
                'type'      => 'return',
                'reference' => $r->return_number,
                'note'      => $r->note,
                'method'    => null,
                'debit'     => 0.0,
                'credit'    => (float) $r->total_amount,
            ]);
        }

        $entries = $this->withRunningBalance(
            $rows->sortBy([['date', 'asc'], ['type', 'asc']])->values(),
            $opening,
            fn($balance, $row) => $balance + $row['debit'] - $row['credit']
        );

        return [
            'customer' => [
                'id'      => $customer->id,
                'code'    => $customer->code,
                'name'    => $customer->name,
                'phone'   => $customer->phone,
                'email'   => $customer->email,
                'address' => $customer->address,
            ],
            'period'  => ['from' => $from, 'to' => $to],
            'opening' => round($opening, 2),
            'rows'    => $entries,
            'totals'  => [
                'debit'  => round($entries->sum('debit'),  2),
                'credit' => round($entries->sum('credit'), 2),
            ],
            'closing' => round($entries->last()['balance'] ?? $opening, 2),
        ];
    }

    public function supplier(int $supplierId, ?string $from, ?string $to): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        $opening  = $this->supplierOpening($supplierId, $from);

        $purchases = Purchase::where('supplier_id', $supplierId)
            ->where('status', 'received')
            ->when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->orderBy('purchase_date')->orderBy('id')
            ->get(['id', 'purchase_number', 'purchase_date', 'total_amount', 'reference', 'notes']);

        $payments = SupplierPayment::where('supplier_id', $supplierId)
            ->when($from, fn($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('payment_date', '<=', $to))
            ->orderBy('payment_date')->orderBy('id')
            ->get(['id', 'amount', 'payment_date', 'payment_method', 'reference', 'note']);

        $returns = PurchaseReturn::where('supplier_id', $supplierId)
            ->when($from, fn($q) => $q->whereDate('return_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('return_date', '<=', $to))
            ->orderBy('return_date')->orderBy('id')
            ->get(['id', 'return_number', 'return_date', 'total_amount', 'note']);

        $rows = collect();

        foreach ($purchases as $p) {
            $rows->push([
                'date'      => $p->purchase_date->format('Y-m-d'),
                'type'      => 'purchase',
                'reference' => $p->purchase_number,
                'note'      => $p->notes ?: $p->reference,
                'method'    => null,
                'debit'     => 0.0,
                'credit'    => (float) $p->total_amount,
            ]);
        }

        foreach ($payments as $pay) {
            $rows->push([
                'date'      => $pay->payment_date->format('Y-m-d'),
                'type'      => 'payment',
                'reference' => $pay->reference,
                'note'      => $pay->note,
                'method'    => $pay->payment_method,
                'debit'     => (float) $pay->amount,
                'credit'    => 0.0,
            ]);
        }

        foreach ($returns as $r) {
            $rows->push([
                'date'      => $r->return_date->format('Y-m-d'),
                'type'      => 'return',
                'reference' => $r->return_number,
                'note'      => $r->note,
                'method'    => null,
                'debit'     => (float) $r->total_amount,
                'credit'    => 0.0,
            ]);
        }

        $entries = $this->withRunningBalance(
            $rows->sortBy([['date', 'asc'], ['type', 'asc']])->values(),
            $opening,
            fn($balance, $row) => $balance + $row['credit'] - $row['debit']
        );

        return [
            'supplier' => [
                'id'             => $supplier->id,
                'code'           => $supplier->code,
                'name'           => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone'          => $supplier->phone,
                'address'        => $supplier->address,
            ],
            'period'  => ['from' => $from, 'to' => $to],
            'opening' => round($opening, 2),
            'rows'    => $entries,
            'totals'  => [
                'debit'  => round($entries->sum('debit'),  2),
                'credit' => round($entries->sum('credit'), 2),
            ],
            'closing' => round($entries->last()['balance'] ?? $opening, 2),
        ];
    }

    public function product(int $productId, ?string $from, ?string $to, ?int $branchId = null): array
    {
        $product = Product::with('unit')->findOrFail($productId);
        $opening = $this->productOpening($productId, $from, $branchId);

        $movements = collect();

        // wareneingang
        PurchaseItem::query()
            ->select('purchase_items.quantity', 'purchase_items.unit_cost as rate',
                     'purchases.purchase_date as date', 'purchases.purchase_number as ref')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $productId)
            ->where('purchases.status', 'received')
            ->whereNull('purchases.deleted_at')
            ->when($from,     fn($q) => $q->whereDate('purchases.purchase_date', '>=', $from))
            ->when($to,       fn($q) => $q->whereDate('purchases.purchase_date', '<=', $to))
            ->when($branchId, fn($q) => $q->where('purchases.branch_id', $branchId))
            ->get()
            ->each(fn($r) => $movements->push([
                'date'      => (string) $r->date,
                'type'      => 'purchase',
                'reference' => $r->ref,
                'qty_in'    => (float) $r->quantity,
                'qty_out'   => 0.0,
                'rate'      => (float) $r->rate,
            ]));

        // verkauf (warenausgang)
        SaleItem::query()
            ->select('sale_items.quantity', 'sale_items.unit_price as rate',
                     'sales.sale_date as date', 'sales.sale_number as ref')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.product_id', $productId)
            ->where('sales.status', 'active')
            ->whereNull('sales.deleted_at')
            ->where('sale_items.is_service', false)
            ->when($from,     fn($q) => $q->whereDate('sales.sale_date', '>=', $from))
            ->when($to,       fn($q) => $q->whereDate('sales.sale_date', '<=', $to))
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->get()
            ->each(fn($r) => $movements->push([
                'date'      => (string) $r->date,
                'type'      => 'sale',
                'reference' => $r->ref,
                'qty_in'    => 0.0,
                'qty_out'   => (float) $r->quantity,
                'rate'      => (float) $r->rate,
            ]));

        PurchaseReturnItem::query()
            ->select('purchase_return_items.quantity', 'purchase_return_items.unit_cost as rate',
                     'purchase_returns.return_date as date', 'purchase_returns.return_number as ref')
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_return_items.product_id', $productId)
            ->whereNull('purchase_returns.deleted_at')
            ->when($from,     fn($q) => $q->whereDate('purchase_returns.return_date', '>=', $from))
            ->when($to,       fn($q) => $q->whereDate('purchase_returns.return_date', '<=', $to))
            ->when($branchId, fn($q) => $q->where('purchase_returns.branch_id', $branchId))
            ->get()
            ->each(fn($r) => $movements->push([
                'date'      => (string) $r->date,
                'type'      => 'purchase_return',
                'reference' => $r->ref,
                'qty_in'    => 0.0,
                'qty_out'   => (float) $r->quantity,
                'rate'      => (float) $r->rate,
            ]));

        SaleReturnItem::query()
            ->select('sale_return_items.quantity', 'sale_return_items.unit_price as rate',
                     'sale_returns.return_date as date', 'sale_returns.return_number as ref')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_return_items.product_id', $productId)
            ->whereNull('sale_returns.deleted_at')
            ->when($from,     fn($q) => $q->whereDate('sale_returns.return_date', '>=', $from))
            ->when($to,       fn($q) => $q->whereDate('sale_returns.return_date', '<=', $to))
            ->when($branchId, fn($q) => $q->where('sale_returns.branch_id', $branchId))
            ->get()
            ->each(fn($r) => $movements->push([
                'date'      => (string) $r->date,
                'type'      => 'sale_return',
                'reference' => $r->ref,
                'qty_in'    => (float) $r->quantity,
                'qty_out'   => 0.0,
                'rate'      => (float) $r->rate,
            ]));

        $entries = $this->withRunningBalance(
            $movements->sortBy([['date', 'asc'], ['type', 'asc']])->values(),
            $opening,
            fn($balance, $row) => $balance + $row['qty_in'] - $row['qty_out']
        );

        return [
            'product' => [
                'id'            => $product->id,
                'sku'           => $product->sku,
                'name'          => $product->name,
                'unit_name'     => $product->unit?->name,
                'unit_symbol'   => $product->unit?->symbol,
                'cost_price'    => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
            ],
            'period'    => ['from' => $from, 'to' => $to],
            'branch_id' => $branchId,
            'opening'   => round($opening, 2),
            'rows'      => $entries,
            'totals'    => [
                'qty_in'  => round($entries->sum('qty_in'),  2),
                'qty_out' => round($entries->sum('qty_out'), 2),
            ],
            'closing'   => round($entries->last()['balance'] ?? $opening, 2),
        ];
    }

    private function withRunningBalance($rows, float $opening, callable $apply)
    {
        $balance = $opening;
        return $rows->map(function ($row) use (&$balance, $apply) {
            $balance = $apply($balance, $row);
            $row['balance'] = round($balance, 2);
            return $row;
        });
    }

    private function customerOpening(int $customerId, ?string $from): float
    {
        if (!$from) {
            return 0.0;
        }

        $sales = (float) Sale::where('customer_id', $customerId)
            ->where('status', 'active')
            ->whereDate('sale_date', '<', $from)
            ->sum('grand_total');

        $paid = (float) CustomerPayment::where('customer_id', $customerId)
            ->whereDate('payment_date', '<', $from)
            ->sum('amount');

        $returned = (float) SaleReturn::where('customer_id', $customerId)
            ->whereDate('return_date', '<', $from)
            ->sum('total_amount');

        return $sales - $paid - $returned;
    }

    private function supplierOpening(int $supplierId, ?string $from): float
    {
        if (!$from) {
            return 0.0;
        }

        $bills = (float) Purchase::where('supplier_id', $supplierId)
            ->where('status', 'received')
            ->whereDate('purchase_date', '<', $from)
            ->sum('total_amount');

        $paid = (float) SupplierPayment::where('supplier_id', $supplierId)
            ->whereDate('payment_date', '<', $from)
            ->sum('amount');

        $returned = (float) PurchaseReturn::where('supplier_id', $supplierId)
            ->whereDate('return_date', '<', $from)
            ->sum('total_amount');

        return $bills - $paid - $returned;
    }

    private function productOpening(int $productId, ?string $from, ?int $branchId): float
    {
        if (!$from) {
            return 0.0;
        }

        $in = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $productId)
            ->where('purchases.status', 'received')
            ->whereNull('purchases.deleted_at')
            ->whereDate('purchases.purchase_date', '<', $from)
            ->when($branchId, fn($q) => $q->where('purchases.branch_id', $branchId))
            ->sum('purchase_items.quantity');

        $out = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.product_id', $productId)
            ->where('sales.status', 'active')
            ->whereNull('sales.deleted_at')
            ->where('sale_items.is_service', false)
            ->whereDate('sales.sale_date', '<', $from)
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->sum('sale_items.quantity');

        $purReturned = PurchaseReturnItem::query()
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_return_items.product_id', $productId)
            ->whereNull('purchase_returns.deleted_at')
            ->whereDate('purchase_returns.return_date', '<', $from)
            ->when($branchId, fn($q) => $q->where('purchase_returns.branch_id', $branchId))
            ->sum('purchase_return_items.quantity');

        $salReturned = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_return_items.product_id', $productId)
            ->whereNull('sale_returns.deleted_at')
            ->whereDate('sale_returns.return_date', '<', $from)
            ->when($branchId, fn($q) => $q->where('sale_returns.branch_id', $branchId))
            ->sum('sale_return_items.quantity');

        return (float) $in - (float) $out - (float) $purReturned + (float) $salReturned;
    }
}
