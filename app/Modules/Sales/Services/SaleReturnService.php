<?php

namespace App\Modules\Sales\Services;

use App\Modules\Product\Models\Inventory;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturn;
use App\Modules\Sales\Models\SaleReturnItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaleReturnService
{
    // ── List ─────────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = SaleReturn::with(['sale', 'customer', 'branch'])
            ->withCount('items');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(fn($s) =>
                $s->where('return_number', 'like', $term)
                  ->orWhereHas('sale', fn($sale) => $sale->where('sale_number', 'like', $term))
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', $term))
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

    // ── Return details for a given sale ──────────────────────────────────

    public function getReturnDetails(int $saleId): array
    {
        $sale = Sale::with(['items.product.unit', 'customer', 'branch'])
            ->findOrFail($saleId);

        if ($sale->status !== 'active') {
            throw new \RuntimeException('Nur aktive Verkäufe können zurückgegeben werden.');
        }

        $items = $sale->items->map(function (SaleItem $item) {
            $alreadyReturned = SaleReturnItem::where('sale_item_id', $item->id)
                ->sum('quantity');

            return [
                'sale_item_id'       => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product?->name ?? '—',
                'product_sku'        => $item->product?->sku  ?? '',
                'image_url'          => $item->product?->image
                                          ? Storage::url($item->product->image)
                                          : null,
                'unit_name'          => $item->product?->unit?->name   ?? '',
                'unit_symbol'        => $item->product?->unit?->symbol ?? '',
                'original_qty'       => (float) $item->quantity,
                'unit_price'         => (float) $item->unit_price,
                'already_returned'   => (float) $alreadyReturned,
                'available_to_return'=> max(0, (float) $item->quantity - (float) $alreadyReturned),
                'return_qty'         => 0,
                'line_total'         => 0,
            ];
        })->filter(fn($i) => $i['available_to_return'] > 0)->values();

        $customerName = $sale->customer?->name ?? $sale->customer_name ?? null;

        return [
            'sale' => [
                'id'           => $sale->id,
                'sale_number'  => $sale->sale_number,
                'sale_date'    => $sale->sale_date?->format('Y-m-d'),
                'branch_name'  => $sale->branch?->name,
                'customer'     => $customerName ? [
                    'id'    => $sale->customer_id,
                    'name'  => $customerName,
                    'phone' => $sale->customer_phone,
                ] : null,
            ],
            'items' => $items,
        ];
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function store(array $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            $returnItems = array_filter(
                $data['items'] ?? [],
                fn($i) => (float) ($i['return_qty'] ?? 0) > 0
            );

            if (empty($returnItems)) {
                throw new \RuntimeException('Bitte mindestens eine Rückgabemenge eingeben.');
            }

            $sale = Sale::with('items')->findOrFail($data['sale_id']);

            if ($sale->status !== 'active') {
                throw new \RuntimeException('Nur aktive Verkäufe können zurückgegeben werden.');
            }

            // Validate each line
            foreach ($returnItems as $row) {
                $item = SaleItem::findOrFail($row['sale_item_id']);
                $alreadyReturned = SaleReturnItem::where('sale_item_id', $item->id)
                    ->sum('quantity');
                $available = (float) $item->quantity - (float) $alreadyReturned;

                if ((float) $row['return_qty'] > $available) {
                    $name = $item->product?->name ?? "Produkt #{$item->product_id}";
                    throw new \RuntimeException(
                        "Rückgabemenge für \"{$name}\" überschreitet verkaufte Menge ({$available})."
                    );
                }
            }

            // Calculate total
            $total = array_sum(array_map(
                fn($i) => round((float)$i['return_qty'] * (float)$i['unit_price'], 2),
                $returnItems
            ));

            $saleReturn = SaleReturn::create([
                'return_number' => $this->generateNumber(),
                'return_date'   => $data['return_date'],
                'sale_id'       => $sale->id,
                'branch_id'     => $sale->branch_id,
                'customer_id'   => $sale->customer_id,
                'total_amount'  => round($total, 2),
                'note'          => $data['note'] ?? null,
                'created_by'    => auth()->id(),
            ]);

            foreach ($returnItems as $row) {
                $qty       = (float) $row['return_qty'];
                $price     = (float) $row['unit_price'];
                $lineTotal = round($qty * $price, 2);

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id'   => $row['sale_item_id'],
                    'product_id'     => $row['product_id'],
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'line_total'     => $lineTotal,
                ]);

                // Customer returns goods → stock increases
                Inventory::where('product_id', $row['product_id'])
                         ->where('branch_id', $sale->branch_id)
                         ->increment('quantity', $qty);
            }

            return $saleReturn->fresh(['sale', 'customer', 'branch', 'items.product']);
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function generateNumber(): string
    {
        $year = now()->year;
        $last = SaleReturn::withTrashed()
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('return_number');

        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return sprintf('VKR-%d-%05d', $year, $seq);
    }
}
