<?php

namespace App\Modules\Serials\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSerialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'serial_number'        => $this->serial_number,
            'status'               => $this->status,
            'product_id'           => $this->product_id,
            'product_name'         => $this->whenLoaded('product', fn () => $this->product->name),
            'product_sku'          => $this->whenLoaded('product', fn () => $this->product->sku),
            'branch_id'            => $this->branch_id,
            'branch_name'          => $this->whenLoaded('branch',  fn () => $this->branch?->name),
            'purchase_id'          => $this->purchase_id,
            'sale_id'              => $this->sale_id,
            'customer_id'          => $this->customer_id,
            'supplier_id'          => $this->supplier_id,
            'purchase_date'        => optional($this->purchase_date)->toDateString(),
            'sale_date'            => optional($this->sale_date)->toDateString(),
            'warranty_months'      => $this->warranty_months,
            'warranty_expiry_date' => optional($this->warranty_expiry_date)->toDateString(),
            'warranty_remaining_days' => $this->warrantyRemainingDays(),
            'is_under_warranty'    => $this->isUnderWarranty(),
            'notes'                => $this->notes,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
