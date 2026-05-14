<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:120'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'hierarchy_level' => ['nullable', 'integer', 'min:0', 'max:99'],
            'description'     => ['nullable', 'string', 'max:255'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }
}
