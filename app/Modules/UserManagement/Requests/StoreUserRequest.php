<?php

namespace App\Modules\UserManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['required', 'in:admin,manager,cashier'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'   => 'An account with this email address already exists.',
            'role.in'        => 'Role must be one of: admin, manager, cashier.',
            'branch_id.exists' => 'The selected branch does not exist.',
        ];
    }
}
