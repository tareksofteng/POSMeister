<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('department')?->id;

        return [
            'name'        => ['required', 'string', 'max:120', "unique:departments,name,{$id},id,deleted_at,NULL"],
            'code'        => ['nullable', 'string', 'max:30',  "unique:departments,code,{$id},id,deleted_at,NULL"],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
