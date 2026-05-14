<?php

namespace App\Modules\HRM\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'start_time'      => substr($this->start_time, 0, 5),
            'end_time'        => substr($this->end_time, 0, 5),
            'grace_minutes'   => (int) $this->grace_minutes,
            'is_active'       => (bool) $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
