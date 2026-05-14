<?php

namespace App\Modules\HRM\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'department_id'   => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn() => $this->department?->name),
            'hierarchy_level' => $this->hierarchy_level,
            'description'     => $this->description,
            'is_active'       => (bool) $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
