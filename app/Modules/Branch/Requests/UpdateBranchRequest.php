<?php

namespace App\Modules\Branch\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:20', 'alpha_num',
                            Rule::unique('branches', 'code')->ignore($this->route('branch'))],
            'name'      => ['required', 'string', 'max:200'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'address'   => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
