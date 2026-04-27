<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Product\Models\Inventory;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseReturnService
{
    // ── List ─────────────────────────────────────────────────────────────

    public function find(int $id): PurchaseReturn
    {
        return PurchaseReturn::with(['purchase', 'supplier', 'branch', 'items.product.unit', 'creator'])
            ->findOrFail($id);
    }

    public function record(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $q = PurchaseReturn::with(['purchase', 'supplier', 'branch', 'items.product.unit'])
            ->orderByDesc('return_date')->orderByDesc('id');

        if (!empty($filters['date_from'])) {
            $q->whereDate('return_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('return_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['supplier_id'])) {
            $q->where('supplier_id', $filters['supplier_id']);
        }

        return $q->get();
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = PurchaseReturn::with(['purchase', 'supplier', 'branch'])
            ->withCount('items');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(fn($s) =>
                $s->where('return_number', 'like', $term)
                  ->orWhereHas('purchase', fn($p) => $p->where('purchase_number', 'like', $term))
                  ->orWhereHas('supplier', fn($sup) => $sup->where('name', 'like', $term))
            );
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('return_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $q->whereDate('return_date', '<=', $filters['date_to']);
        }

        return $q->orderByDesc('return_date')->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    // ── Return details for a given purchase ──────────────────────────────

    public function getReturnDetails(int $purchaseId): array
    {
        $purchase = Purchase::with(['items.product.unit', 'supplier', 'branch'])
            ->findOrFail($purchaseId);

        // Only received purchases can be returned
        if ($purchase->isDraft()) {
            throw new \RuntimeException('Only received purchases can have returns.');
        }

        $items = $purchase->items->map(function (PurchaseItem $item) {
            $alreadyReturned = PurchaseReturnItem::where('purchase_item_id', $item->id)
                ->sum('quantity');

            return [
                'purchase_item_id'   => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product?->name ?? '—',
                'product_sku'        => $item->product?->sku  ?? '',
                'image_url'          => $item->product?->image
                                          ? Storage::url($item->product->image)
                                          : null,
                'unit_name'          => $item->product?->unit?->name   ?? '',
                'unit_symbol'        => $item->product?->unit?->symbol ?? '',
                'original_qty'       => (float) $item->quantity,
                'unit_cost'          => (float) $item->unit_cost,
                'already_returned'   => (float) $alreadyReturned,
                'available_to_return'=> max(0, (float) $item->quantity - (float) $alreadyReturned),
                'return_qty'         => 0,
                'line_total'         => 0,
            ];
        })->filter(fn($i) => $i['available_to_return'] > 0)->values();

        return [
            'purchase' => [
                'id'              => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'purchase_date'   => $purchase->purchase_date?->format('Y-m-d'),
                'branch_name'     => $purchase->branch?->name,
                'supplier'        => $purchase->supplier ? [
                    'id'      => $purchase->supplier->id,
                    'name'    => $purchase->supplier->name,
                    'phone'   => $purchase->supplier->phone,
                    'address' => $purchase->supplier->address,
                ] : null,
            ],
            'items' => $items,
        ];
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function store(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $returnItems = array_filter(
                $data['items'] ?? [],
                fn($i) => (float) ($i['return_qty'] ?? 0) > 0
            );

            if (empty($returnItems)) {
                throw new \RuntimeException('Bitte mindestens eine Rückgabemenge eingeben.');
            }

            $purchase = Purchase::with('items')->findOrFail($data['purchase_id']);

            // Validate each line
            foreach ($returnItems as $row) {
                $item = PurchaseItem::findOrFail($row['purchase_item_id']);
                $alreadyReturned = PurchaseReturnItem::where('purchase_item_id', $item->id)
                    ->sum('quantity');
                $available = (float) $item->quantity - (float) $alreadyReturned;

                if ((float) $row['return_qty'] > $available) {
                    $name = $item->product?->name ?? "Produkt #{$item->product_id}";
                    throw new \RuntimeException(
                        "Rückgabemenge für \"{$name}\" überschreitet verfügbare Menge ({$available})."
                    );
                }
            }

            // Calculate total
            $total = array_sum(array_map(
                fn($i) => round((float)$i['return_qty'] * (float)$i['unit_cost'], 2),
                $returnItems
            ));

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $this->generateNumber(),
                'return_date'   => $data['return_date'],
                'purchase_id'   => $purchase->id,
                'branch_id'     => $purchase->branch_id,
                'supplier_id'   => $purchase->supplier_id,
                'total_amount'  => round($total, 2),
                'note'          => $data['note'] ?? null,
                'created_by'    => auth()->id(),
            ]);

            foreach ($returnItems as $row) {
                $qty       = (float) $row['return_qty'];
                $cost      = (float) $row['unit_cost'];
                $lineTotal = round($qty * $cost, 2);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_item_id'   => $row['purchase_item_id'],
                    'product_id'         => $row['product_id'],
                    'quantity'           => $qty,
                    'unit_cost'          => $cost,
                    'line_total'         => $lineTotal,
                ]);

                // Return to supplier → stock decreases
                Inventory::where('product_id', $row['product_id'])
                         ->where('branch_id', $purchase->branch_id)
                         ->decrement('quantity', $qty);
            }

            return $purchaseReturn->fresh(['purchase', 'supplier', 'branch', 'items.product']);
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function generateNumber(): string
    {
        $year = now()->year;
        $last = PurchaseReturn::withTrashed()
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('return_number');

        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return sprintf('EKR-%d-%05d', $year, $seq);
    }
}
