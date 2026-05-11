<?php

namespace App\Modules\HRM\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmployeeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'employee_id'       => $this->employee_id,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'full_name'         => $this->full_name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'emergency_contact' => $this->emergency_contact,

            'gender'            => $this->gender,
            'date_of_birth'     => $this->date_of_birth?->format('Y-m-d'),
            'blood_group'       => $this->blood_group,
            'marital_status'    => $this->marital_status,
            'nationality'       => $this->nationality,
            'religion'          => $this->religion,

            'address'           => $this->address,
            'city'              => $this->city,
            'postal_code'       => $this->postal_code,
            'country'           => $this->country,

            'joining_date'      => $this->joining_date?->format('Y-m-d'),
            'employment_type'   => $this->employment_type,
            'basic_salary'      => (float) $this->basic_salary,

            'designation_id'    => $this->designation_id,
            'department_id'     => $this->department_id,
            'branch_id'         => $this->branch_id,
            'shift_id'          => $this->shift_id,

            'designation' => $this->whenLoaded('designation', fn() => $this->designation ? [
                'id'    => $this->designation->id,
                'title' => $this->designation->title,
            ] : null),
            'department' => $this->whenLoaded('department', fn() => $this->department ? [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn() => $this->branch ? [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'shift' => $this->whenLoaded('shift', fn() => $this->shift ? [
                'id'         => $this->shift->id,
                'name'       => $this->shift->name,
                'start_time' => substr($this->shift->start_time, 0, 5),
                'end_time'   => substr($this->shift->end_time,   0, 5),
            ] : null),

            'photo'             => $this->photo,
            'photo_url'         => $this->photo ? Storage::url($this->photo) : null,
            'national_id'       => $this->national_id,
            'passport_number'   => $this->passport_number,
            'work_permit_no'    => $this->work_permit_no,

            'status'            => $this->status,
            'notes'             => $this->notes,

            'created_by_name'   => $this->whenLoaded('creator', fn() => $this->creator?->name, '—'),
            'created_at'        => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
