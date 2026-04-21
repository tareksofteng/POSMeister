<?php

namespace App\Modules\Sales\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Product\Models\Inventory;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SaleService
{
    // ── List ──────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Sale::with(['customer', 'branch'])
            ->withCount('items');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(fn($s) =>
                $s->where('sale_number', 'like', $term)
                  ->orWhere('customer_name', 'like', $term)
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', $term))
            );
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('sale_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $q->whereDate('sale_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }

        return $q->orderByDesc('sale_date')
                 ->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Sale
    {
        return Sale::with(['items.product.unit', 'branch', 'customer', 'creator'])
            ->findOrFail($id);
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function store(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $items    = $data['items'] ?? [];
            $discount = (float) ($data['discount_amount'] ?? 0);
            $freight  = (float) ($data['freight_amount']  ?? 0);
            $totals   = SaleCalculator::calculate($items, $discount, $freight);

            $branchId = $data['branch_id']
                ?? auth()->user()->branch_id
                ?? Branch::where('is_active', true)->value('id');

            // Stock validation before writing anything
            foreach ($items as $item) {
                if ($item['is_service'] ?? false) {
                    continue;
                }
                $inv = Inventory::where(['product_id' => $item['product_id'], 'branch_id' => $branchId])
                    ->first();
                $available = $inv ? (float) $inv->quantity : 0;
                if ($available < (float) $item['quantity']) {
                    $name = Product::find($item['product_id'])?->name ?? "Produkt #{$item['product_id']}";
                    throw new \RuntimeException("Unzureichender Bestand für: {$name}");
                }
            }

            $totalPaid = (float) ($data['cash_paid'] ?? 0) + (float) ($data['card_paid'] ?? 0);

            $sale = Sale::create([
                'sale_number'      => $this->generateNumber(),
                'sale_date'        => $data['sale_date'],
                'branch_id'        => $branchId,
                'customer_id'      => $data['customer_id']      ?? null,
                'customer_name'    => $data['customer_name']    ?? null,
                'customer_phone'   => $data['customer_phone']   ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'customer_type'    => $data['customer_type']    ?? 'walkin',
                'sale_type'        => $data['sale_type']        ?? 'retail',
                'subtotal'         => $totals['subtotal'],
                'discount_amount'  => $discount,
                'vat_amount'       => $totals['vat_amount'],
                'freight_amount'   => $freight,
                'grand_total'      => $totals['grand_total'],
                'cash_paid'        => $data['cash_paid'] ?? 0,
                'card_paid'        => $data['card_paid'] ?? 0,
                'total_paid'       => $totalPaid,
                'due_amount'       => max(0, $totals['grand_total'] - $totalPaid),
                'previous_due'     => $data['previous_due'] ?? 0,
                'note'             => $data['note'] ?? null,
                'status'           => 'active',
                'created_by'       => auth()->id(),
            ]);

            foreach ($items as $item) {
                $amounts = SaleCalculator::lineAmounts(
                    (float) $item['quantity'],
                    (float) $item['unit_price'],
                    (float) ($item['tax_rate'] ?? 0)
                );

                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'] ?? 0,
                    'tax_rate'   => $item['tax_rate']   ?? 0,
                    'vat_amount' => $amounts['vat_amount'],
                    'line_total' => $amounts['line_total'],
                    'is_service' => $item['is_service'] ?? false,
                ]);

                // Deduct from inventory
                if (!($item['is_service'] ?? false)) {
                    Inventory::where(['product_id' => $item['product_id'], 'branch_id' => $branchId])
                        ->decrement('quantity', (float) $item['quantity']);
                }
            }

            return $this->find($sale->id);
        });
    }

    // ── Cancel ────────────────────────────────────────────────────────────

    public function cancel(Sale $sale): Sale
    {
        if (!$sale->isActive()) {
            throw new \RuntimeException('Dieser Verkauf ist bereits storniert.');
        }

        return DB::transaction(function () use ($sale) {
            // Restore inventory
            foreach ($sale->items as $item) {
                if (!$item->is_service) {
                    Inventory::where(['product_id' => $item->product_id, 'branch_id' => $sale->branch_id])
                        ->increment('quantity', (float) $item->quantity);
                }
            }

            $sale->update([
                'status'       => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            return $sale->fresh();
        });
    }

    // ── POS product search (returns fields needed for cart) ───────────────

    public function posProductSearch(string $term, int $branchId): array
    {
        $products = Product::with('unit')
            ->active()
            ->where(fn($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', $term)
            )
            ->limit(12)
            ->get(['id', 'sku', 'name', 'image', 'selling_price', 'wholesale_price',
                   'cost_price', 'tax_rate', 'unit_id', 'is_service', 'reorder_level']);

        return $products->map(function ($p) use ($branchId) {
            $inv = Inventory::where(['product_id' => $p->id, 'branch_id' => $branchId])->first();
            return [
                'id'             => $p->id,
                'sku'            => $p->sku,
                'name'           => $p->name,
                'image_url'      => $p->image ? \Storage::url($p->image) : null,
                'selling_price'  => (float) $p->selling_price,
                'wholesale_price'=> (float) $p->wholesale_price,
                'cost_price'     => (float) $p->cost_price,
                'tax_rate'       => (float) $p->tax_rate,
                'unit_name'      => $p->unit?->name ?? '',
                'unit_symbol'    => $p->unit?->symbol ?? '',
                'is_service'     => (bool) $p->is_service,
                'stock'          => $inv ? (float) $inv->quantity : 0,
            ];
        })->toArray();
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function generateNumber(): string
    {
        $year  = now()->format('Y');
        $prefix = "VK-{$year}-";
        $last  = Sale::withTrashed()
            ->where('sale_number', 'like', "{$prefix}%")
            ->max('sale_number');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
