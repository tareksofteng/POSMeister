<?php

namespace App\Modules\UserManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200',
                            Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone'     => ['nullable', 'string', 'max:20'],
            // Password is optional on update — only validate if provided
            'password'  => ['nullable', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['required', 'in:admin,manager,cashier'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['boolean'],
        ];
    }
}
