<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'name'           => $this->name,
            'contact_person' => $this->contact_person,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'city'           => $this->city,
            'country'        => $this->country,
            'vat_number'     => $this->vat_number,
            'notes'          => $this->notes,
            'is_active'      => $this->is_active,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
