<?php

namespace App\Modules\Sales\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SaleReturnResource extends JsonResource
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

            'sale' => $this->whenLoaded('sale', fn() => [
                'id'          => $this->sale->id,
                'sale_number' => $this->sale->sale_number,
                'sale_date'   => $this->sale->sale_date?->format('Y-m-d'),
            ]),

            'customer' => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
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
                'unit_price'   => (float) $item->unit_price,
                'line_total'   => (float) $item->line_total,
            ])),
        ];
    }
}
