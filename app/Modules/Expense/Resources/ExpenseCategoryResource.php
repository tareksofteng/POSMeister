<?php

namespace App\Modules\Expense\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'description'     => $this->description,
            'is_active'       => (bool) $this->is_active,
            'expenses_count'  => $this->whenCounted('expenses'),
        ];
    }
}
