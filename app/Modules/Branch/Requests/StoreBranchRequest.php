<?php

namespace App\Modules\Branch\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:20', 'unique:branches,code', 'alpha_num'],
            'name'      => ['required', 'string', 'max:200'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'address'   => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.alpha_num' => 'Branch code must contain only letters and numbers (e.g. BR01).',
            'code.unique'    => 'This branch code is already taken.',
        ];
    }
}
