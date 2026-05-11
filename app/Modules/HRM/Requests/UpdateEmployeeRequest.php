<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id ?? $this->route('id');

        return [
            'first_name'        => ['sometimes', 'required', 'string', 'max:80'],
            'last_name'         => ['sometimes', 'required', 'string', 'max:80'],
            'email'             => [
                'nullable', 'email', 'max:150',
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'phone'             => ['nullable', 'string', 'max:30'],
            'emergency_contact' => ['nullable', 'string', 'max:120'],
            'gender'            => ['sometimes', 'in:male,female,other'],
            'date_of_birth'     => ['nullable', 'date', 'before:today'],
            'blood_group'       => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'marital_status'    => ['nullable', 'in:single,married,divorced,widowed'],
            'nationality'       => ['nullable', 'string', 'max:80'],
            'religion'          => ['nullable', 'string', 'max:50'],

            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:80'],
            'postal_code'       => ['nullable', 'string', 'max:20'],
            'country'           => ['nullable', 'string', 'max:80'],

            'joining_date'      => ['sometimes', 'required', 'date'],
            'employment_type'   => ['sometimes', 'in:full_time,part_time,contract,intern'],
            'designation_id'    => ['nullable', 'integer', 'exists:designations,id'],
            'department_id'     => ['nullable', 'integer', 'exists:departments,id'],
            'branch_id'         => ['nullable', 'integer', 'exists:branches,id'],
            'shift_id'          => ['nullable', 'integer', 'exists:shifts,id'],
            'basic_salary'      => ['nullable', 'numeric', 'min:0'],

            'photo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'national_id'       => ['nullable', 'string', 'max:50'],
            'passport_number'   => ['nullable', 'string', 'max:50'],
            'work_permit_no'    => ['nullable', 'string', 'max:50'],

            'status'            => ['nullable', 'in:active,inactive,terminated,resigned'],
            'notes'             => ['nullable', 'string'],
        ];
    }
}
