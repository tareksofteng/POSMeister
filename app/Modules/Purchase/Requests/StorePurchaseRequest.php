<?php

namespace App\Modules\Purchase\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'     => ['nullable', 'exists:suppliers,id'],
            'purchase_date'   => ['required', 'date'],
            'reference'       => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'freight_amount'  => ['nullable', 'numeric', 'min:0'],
            'receive'         => ['nullable', 'boolean'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'exists:products,id'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost'      => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate'       => ['nullable', 'numeric', 'in:0,7,19'],
        ];
    }
}
