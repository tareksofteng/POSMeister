<?php

namespace App\Modules\Sales\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'sale_number'      => $this->sale_number,
            'sale_date'        => $this->sale_date?->format('Y-m-d'),
            'status'           => $this->status,
            'sale_type'        => $this->sale_type,
            'customer_type'    => $this->customer_type,

            // Customer
            'customer_id'      => $this->customer_id,
            'customer_name'    => $this->display_customer_name,
            'customer_phone'   => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'customer' => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'code'  => $this->customer->code,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),

            // Branch
            'branch_name' => $this->whenLoaded('branch', fn() => $this->branch?->name),

            // Monetary
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'vat_amount'      => (float) $this->vat_amount,
            'freight_amount'  => (float) $this->freight_amount,
            'grand_total'     => (float) $this->grand_total,
            'cash_paid'       => (float) $this->cash_paid,
            'card_paid'       => (float) $this->card_paid,
            'total_paid'      => (float) $this->total_paid,
            'due_amount'      => (float) $this->due_amount,
            'previous_due'    => (float) $this->previous_due,

            'note'            => $this->note,
            'items_count'     => $this->whenCounted('items'),
            'cancelled_at'    => $this->cancelled_at?->format('Y-m-d H:i'),
            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),

            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'          => $item->id,
                'product_id'  => $item->product_id,
                'name'        => $item->product?->name ?? '—',
                'sku'         => $item->product?->sku  ?? '—',
                'image_url'   => $item->product?->image ? Storage::url($item->product->image) : null,
                'unit_name'   => $item->product?->unit?->name   ?? '',
                'unit_symbol' => $item->product?->unit?->symbol ?? '',
                'quantity'    => (float) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'cost_price'  => (float) $item->cost_price,
                'tax_rate'    => (float) $item->tax_rate,
                'vat_amount'  => (float) $item->vat_amount,
                'line_total'  => (float) $item->line_total,
                'is_service'  => $item->is_service,
            ])),
        ];
    }
}
