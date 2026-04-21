<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Product\Models\Inventory;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    // ── List ──────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Purchase::with(['supplier', 'branch'])
            ->withCount('items');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('purchase_number', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', $term));
            });
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $q->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('purchase_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $q->whereDate('purchase_date', '<=', $filters['date_to']);
        }

        return $q->orderByDesc('purchase_date')
                 ->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Purchase
    {
        return Purchase::with([
            'supplier',
            'branch',
            'items.product.unit',
            'creator',
        ])->findOrFail($id);
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function store(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $items    = $data['items'] ?? [];
            $totals   = $this->calculateTotals($items, $data);
            $branchId = $data['branch_id']
                ?? auth()->user()->branch_id
                ?? Branch::where('is_active', true)->value('id');

            $purchase = Purchase::create([
                'purchase_number' => $this->generateNumber(),
                'branch_id'       => $branchId,
                'supplier_id'     => $data['supplier_id']      ?? null,
                'purchase_date'   => $data['purchase_date'],
                'status'          => 'draft',
                'reference'       => $data['reference']        ?? null,
                'notes'           => $data['notes']            ?? null,
                'discount_amount' => $data['discount_amount']  ?? 0,
                'freight_amount'  => $data['freight_amount']   ?? 0,
                'subtotal'        => $totals['subtotal'],
                'vat_amount'      => $totals['vat_amount'],
                'total_amount'    => $totals['total_amount'],
            ]);

            $this->syncItems($purchase, $items);

            if (!empty($data['receive']) && $data['receive'] === true) {
                $this->receiveStock($purchase);
            }

            return $purchase->fresh(['supplier', 'branch', 'items.product']);
        });
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(Purchase $purchase, array $data): Purchase
    {
        if (!$purchase->isDraft()) {
            throw new \RuntimeException('Received purchases cannot be edited.');
        }

        return DB::transaction(function () use ($purchase, $data) {
            $items  = $data['items'] ?? [];
            $totals = $this->calculateTotals($items, $data);

            $purchase->update([
                'supplier_id'     => $data['supplier_id']      ?? $purchase->supplier_id,
                'purchase_date'   => $data['purchase_date']    ?? $purchase->purchase_date,
                'reference'       => $data['reference']        ?? null,
                'notes'           => $data['notes']            ?? null,
                'discount_amount' => $data['discount_amount']  ?? 0,
                'freight_amount'  => $data['freight_amount']   ?? 0,
                'subtotal'        => $totals['subtotal'],
                'vat_amount'      => $totals['vat_amount'],
                'total_amount'    => $totals['total_amount'],
            ]);

            $this->syncItems($purchase, $items);

            if (!empty($data['receive']) && $data['receive'] === true) {
                $this->receiveStock($purchase);
            }

            return $purchase->fresh(['supplier', 'branch', 'items.product']);
        });
    }

    // ── Receive (mark received + update stock) ────────────────────────────

    public function receive(Purchase $purchase): Purchase
    {
        if (!$purchase->isDraft()) {
            throw new \RuntimeException('Purchase is already received.');
        }

        return DB::transaction(function () use ($purchase) {
            $this->receiveStock($purchase);
            return $purchase->fresh(['supplier', 'branch', 'items.product']);
        });
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(Purchase $purchase): void
    {
        if (!$purchase->isDraft()) {
            throw new \RuntimeException('Received purchases cannot be deleted.');
        }
        $purchase->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function syncItems(Purchase $purchase, array $items): void
    {
        $purchase->items()->delete();

        foreach ($items as $row) {
            $qty      = (float) ($row['quantity']  ?? 0);
            $cost     = (float) ($row['unit_cost'] ?? 0);
            $vatRate  = (float) ($row['vat_rate']  ?? 0);
            $lineBase = $qty * $cost;
            $lineVat  = round($lineBase * ($vatRate / 100), 2);
            $lineTotal= round($lineBase + $lineVat, 2);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id'  => $row['product_id'],
                'quantity'    => $qty,
                'unit_cost'   => $cost,
                'vat_rate'    => $vatRate,
                'vat_amount'  => $lineVat,
                'line_total'  => $lineTotal,
            ]);
        }
    }

    /**
     * Calculate purchase-level totals from line items + header fields.
     *
     * subtotal    = Σ (qty × cost)
     * vat_amount  = Σ per-item VAT
     * total       = subtotal + vat - discount + freight
     */
    private function calculateTotals(array $items, array $data): array
    {
        $subtotal  = 0;
        $vatAmount = 0;

        foreach ($items as $row) {
            $qty      = (float) ($row['quantity']  ?? 0);
            $cost     = (float) ($row['unit_cost'] ?? 0);
            $vatRate  = (float) ($row['vat_rate']  ?? 0);
            $lineBase = $qty * $cost;
            $lineVat  = round($lineBase * ($vatRate / 100), 2);
            $subtotal  += $lineBase;
            $vatAmount += $lineVat;
        }

        $discount = (float) ($data['discount_amount'] ?? 0);
        $freight  = (float) ($data['freight_amount']  ?? 0);
        $total    = round($subtotal + $vatAmount - $discount + $freight, 2);

        return [
            'subtotal'   => round($subtotal,  2),
            'vat_amount' => round($vatAmount, 2),
            'total_amount' => $total,
        ];
    }

    /**
     * Mark purchase as received and increment inventory for each item.
     */
    private function receiveStock(Purchase $purchase): void
    {
        $purchase->update(['status' => 'received']);

        foreach ($purchase->items as $item) {
            Inventory::firstOrCreate(
                ['product_id' => $item->product_id, 'branch_id' => $purchase->branch_id],
                ['quantity' => 0, 'low_stock_alert' => 0]
            );

            // Atomic increment prevents race conditions under concurrent requests
            Inventory::where('product_id', $item->product_id)
                     ->where('branch_id', $purchase->branch_id)
                     ->increment('quantity', $item->quantity);
        }
    }

    private function generateNumber(): string
    {
        $year = now()->year;
        $last = Purchase::withTrashed()
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('purchase_number');

        if (!$last) {
            $seq = 1;
        } else {
            // Format: EK-2026-00001 → extract last 5 digits
            $seq = (int) substr($last, -5) + 1;
        }

        return sprintf('EK-%d-%05d', $year, $seq);
    }
}
