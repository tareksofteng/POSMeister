<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PurchaseReturnResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'return_number'   => $this->return_number,
            'return_date'     => $this->return_date?->format('Y-m-d'),
            'total_amount'    => (float) $this->total_amount,
            'note'            => $this->note,
            'items_count'     => $this->whenCounted('items'),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),
            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),

            'purchase' => $this->whenLoaded('purchase', fn() => [
                'id'              => $this->purchase->id,
                'purchase_number' => $this->purchase->purchase_number,
                'purchase_date'   => $this->purchase->purchase_date?->format('Y-m-d'),
            ]),

            'supplier' => $this->whenLoaded('supplier', fn() => $this->supplier ? [
                'id'      => $this->supplier->id,
                'name'    => $this->supplier->name,
                'phone'   => $this->supplier->phone,
                'address' => $this->supplier->address,
            ] : null),

            'branch_name' => $this->whenLoaded('branch', fn() => $this->branch?->name),

            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product?->name ?? '—',
                'product_sku'  => $item->product?->sku  ?? '',
                'image_url'    => $item->product?->image ? Storage::url($item->product->image) : null,
                'unit_name'    => $item->product?->unit?->name ?? '',
                'quantity'     => (float) $item->quantity,
                'unit_cost'    => (float) $item->unit_cost,
                'line_total'   => (float) $item->line_total,
            ])),
        ];
    }
}
