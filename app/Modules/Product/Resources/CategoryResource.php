<?php

namespace App\Modules\Product\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
