<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'purchase_number' => $this->purchase_number,
            'branch_id'       => $this->branch_id,
            'branch_name'     => $this->whenLoaded('branch', fn() => $this->branch?->name, '—'),
            'supplier_id'     => $this->supplier_id,
            'supplier_name'   => $this->whenLoaded('supplier', fn() => $this->supplier?->name, '—'),
            'supplier'        => $this->whenLoaded('supplier', fn() => $this->supplier ? [
                'id'             => $this->supplier->id,
                'code'           => $this->supplier->code,
                'name'           => $this->supplier->name,
                'contact_person' => $this->supplier->contact_person,
                'email'          => $this->supplier->email,
                'phone'          => $this->supplier->phone,
                'address'        => $this->supplier->address,
                'city'           => $this->supplier->city,
                'country'        => $this->supplier->country,
                'vat_number'     => $this->supplier->vat_number,
            ] : null),
            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),
            'purchase_date'   => $this->purchase_date?->toDateString(),
            'status'          => $this->status,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'vat_amount'      => (float) $this->vat_amount,
            'freight_amount'  => (float) $this->freight_amount,
            'total_amount'    => (float) $this->total_amount,
            'items_count'     => $this->whenCounted('items'),
            'items'           => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product?->name ?? '—',
                    'product_sku'  => $item->product?->sku ?? '',
                    'unit_name'    => $item->product?->unit?->name ?? null,
                    'unit_symbol'  => $item->product?->unit?->symbol ?? null,
                    'image_url'    => $item->product?->image
                                      ? \Storage::url($item->product->image) : null,
                    'quantity'     => (float) $item->quantity,
                    'unit_cost'    => (float) $item->unit_cost,
                    'vat_rate'     => (float) $item->vat_rate,
                    'vat_amount'   => (float) $item->vat_amount,
                    'line_total'   => (float) $item->line_total,
                ])
            ),
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
