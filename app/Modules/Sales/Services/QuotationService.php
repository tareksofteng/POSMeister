<?php

namespace App\Modules\Sales\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Sales\Models\Quotation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    // ── List / Search ────────────────────────────────────────────────────────

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Quotation::with(['customer', 'branch', 'creator'])
            ->withCount('items');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(fn($s) =>
                $s->where('quotation_number', 'like', $term)
                  ->orWhere('customer_name', 'like', $term)
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', $term))
            );
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('quotation_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $q->whereDate('quotation_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }

        return $q->orderByDesc('quotation_date')
                 ->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Quotation
    {
        return Quotation::with(['items.product.unit', 'branch', 'customer', 'creator'])
            ->findOrFail($id);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $items    = $data['items'] ?? [];
            $discount = (float) ($data['discount_amount'] ?? 0);
            $freight  = (float) ($data['freight_amount']  ?? 0);
            $totals   = SaleCalculator::calculate($items, $discount, $freight);

            $branchId = $data['branch_id']
                ?? auth()->user()->branch_id
                ?? Branch::where('is_active', true)->value('id');

            $quotation = Quotation::create([
                'quotation_number' => $this->generateNumber(),
                'quotation_date'   => $data['quotation_date'],
                'valid_until'      => $data['valid_until']      ?? null,
                'branch_id'        => $branchId,
                'customer_id'      => $data['customer_id']      ?? null,
                'customer_name'    => $data['customer_name']    ?? null,
                'customer_phone'   => $data['customer_phone']   ?? null,
                'customer_email'   => $data['customer_email']   ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'quotation_type'   => $data['quotation_type']   ?? 'retail',
                'subtotal'         => $totals['subtotal'],
                'discount_amount'  => $discount,
                'vat_amount'       => $totals['vat_amount'],
                'freight_amount'   => $freight,
                'grand_total'      => $totals['grand_total'],
                'terms'            => $data['terms'] ?? null,
                'note'             => $data['note']  ?? null,
                'status'           => $data['status'] ?? 'draft',
                'created_by'       => auth()->id(),
            ]);

            $this->syncItems($quotation, $items);

            return $this->find($quotation->id);
        });
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Quotation $quotation, array $data): Quotation
    {
        if (!$quotation->isEditable()) {
            throw new \RuntimeException('Dieses Angebot kann nicht mehr bearbeitet werden.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $items    = $data['items'] ?? [];
            $discount = (float) ($data['discount_amount'] ?? 0);
            $freight  = (float) ($data['freight_amount']  ?? 0);
            $totals   = SaleCalculator::calculate($items, $discount, $freight);

            $quotation->update([
                'quotation_date'   => $data['quotation_date']   ?? $quotation->quotation_date,
                'valid_until'      => $data['valid_until']      ?? $quotation->valid_until,
                'customer_id'      => $data['customer_id']      ?? null,
                'customer_name'    => $data['customer_name']    ?? null,
                'customer_phone'   => $data['customer_phone']   ?? null,
                'customer_email'   => $data['customer_email']   ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'quotation_type'   => $data['quotation_type']   ?? $quotation->quotation_type,
                'subtotal'         => $totals['subtotal'],
                'discount_amount'  => $discount,
                'vat_amount'       => $totals['vat_amount'],
                'freight_amount'   => $freight,
                'grand_total'      => $totals['grand_total'],
                'terms'            => $data['terms'] ?? null,
                'note'             => $data['note']  ?? null,
                'status'           => $data['status'] ?? $quotation->status,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $items);

            return $this->find($quotation->id);
        });
    }

    // ── Status transitions ───────────────────────────────────────────────────

    public function updateStatus(Quotation $quotation, string $status): Quotation
    {
        $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        if (!in_array($status, $allowed, true)) {
            throw new \RuntimeException('Ungültiger Status.');
        }
        if ($quotation->status === 'converted') {
            throw new \RuntimeException('Dieses Angebot wurde bereits in einen Verkauf umgewandelt.');
        }

        $quotation->update(['status' => $status]);
        return $quotation->fresh();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(Quotation $quotation): void
    {
        if ($quotation->status === 'converted') {
            throw new \RuntimeException('Umgewandelte Angebote können nicht gelöscht werden.');
        }
        $quotation->delete();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $amounts = SaleCalculator::lineAmounts(
                (float) $item['quantity'],
                (float) $item['unit_price'],
                (float) ($item['tax_rate'] ?? 0)
            );

            $quotation->items()->create([
                'product_id'  => $item['product_id'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $item['tax_rate'] ?? 0,
                'vat_amount'  => $amounts['vat_amount'],
                'line_total'  => $amounts['line_total'],
                'is_service'  => $item['is_service'] ?? false,
            ]);
        }
    }

    private function generateNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = "QT-{$year}-";
        $last   = Quotation::withTrashed()
            ->where('quotation_number', 'like', "{$prefix}%")
            ->max('quotation_number');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
