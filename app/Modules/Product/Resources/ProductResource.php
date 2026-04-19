<?php

namespace App\Modules\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'sku'              => $this->sku,
            'name'             => $this->name,
            'description'      => $this->description,
            'category_id'      => $this->category_id,
            'category_name'    => $this->whenLoaded('category', fn() => $this->category?->name, '—'),
            'brand_id'         => $this->brand_id,
            'brand_name'       => $this->whenLoaded('brand', fn() => $this->brand?->name, '—'),
            'unit_id'          => $this->unit_id,
            'unit_name'        => $this->whenLoaded('unit', fn() => $this->unit?->name, '—'),
            'unit_symbol'      => $this->whenLoaded('unit', fn() => $this->unit?->symbol, ''),
            'barcode'          => $this->barcode,
            'cost_price'       => $this->cost_price,
            'selling_price'    => $this->selling_price,
            'wholesale_price'  => $this->wholesale_price,
            'min_selling_price'=> $this->min_selling_price,
            'tax_rate'         => $this->tax_rate,
            'reorder_level'    => $this->reorder_level,
            'is_service'       => $this->is_service,
            'is_active'        => $this->is_active,
            'profit_margin'    => $this->profit_margin,
            'image'            => $this->image,
            'image_url'        => $this->image ? '/storage/' . $this->image : null,
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}
