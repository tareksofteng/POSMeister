<?php

namespace App\Modules\Sales\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class QuotationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'quotation_number' => $this->quotation_number,
            'quotation_date'   => $this->quotation_date?->format('Y-m-d'),
            'valid_until'      => $this->valid_until?->format('Y-m-d'),
            'status'           => $this->status,
            'quotation_type'   => $this->quotation_type,
            'is_expired'       => $this->valid_until && $this->valid_until->isPast() && $this->status !== 'converted',

            // Customer
            'customer_id'      => $this->customer_id,
            'customer_name'    => $this->display_customer_name,
            'customer_phone'   => $this->customer_phone,
            'customer_email'   => $this->customer_email,
            'customer_address' => $this->customer_address,
            'customer' => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'code'  => $this->customer->code,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ] : null),

            // Branch
            'branch_id'   => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn() => $this->branch?->name),

            // Monetary
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'vat_amount'      => (float) $this->vat_amount,
            'freight_amount'  => (float) $this->freight_amount,
            'grand_total'     => (float) $this->grand_total,

            'terms'           => $this->terms,
            'note'            => $this->note,
            'items_count'     => $this->whenCounted('items'),
            'converted_sale_id' => $this->converted_sale_id,
            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),
            'created_at'      => $this->created_at?->format('Y-m-d H:i'),

            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'          => $item->id,
                'product_id'  => $item->product_id,
                'description' => $item->description,
                'name'        => $item->product?->name ?? $item->description ?? '—',
                'sku'         => $item->product?->sku  ?? '—',
                'image_url'   => $item->product?->image ? Storage::url($item->product->image) : null,
                'unit_name'   => $item->product?->unit?->name   ?? '',
                'unit_symbol' => $item->product?->unit?->symbol ?? '',
                'quantity'    => (float) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'tax_rate'    => (float) $item->tax_rate,
                'vat_amount'  => (float) $item->vat_amount,
                'line_total'  => (float) $item->line_total,
                'is_service'  => (bool) $item->is_service,
            ])),
        ];
    }
}
