<?php

namespace App\Modules\Purchase\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name'           => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:150', "unique:suppliers,email,{$supplierId},id,deleted_at,NULL"],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:100'],
            'vat_number'     => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string'],
            'is_active'      => ['boolean'],
        ];
    }
}
