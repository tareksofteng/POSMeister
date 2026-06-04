<?php

namespace App\Modules\Serials\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachPurchaseSerialsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'purchase_id'       => ['nullable', 'integer', 'exists:purchases,id'],
            'purchase_item_id'  => ['nullable', 'integer'],
            'supplier_id'       => ['nullable', 'integer', 'exists:suppliers,id'],
            'branch_id'         => ['nullable', 'integer', 'exists:branches,id'],
            'expected_quantity' => ['required', 'integer', 'min:1'],
            'purchase_date'     => ['nullable', 'date'],
            'warranty_months'   => ['nullable', 'integer', 'min:0', 'max:240'],
            'serials'           => ['required', 'array', 'min:1'],
            'serials.*'         => ['required', 'string', 'max:100'],
        ];
    }
}
