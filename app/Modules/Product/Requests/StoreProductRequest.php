<?php

namespace App\Modules\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sku'              => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'name'             => ['required', 'string', 'max:150'],
            'description'      => ['nullable', 'string'],
            'category_id'      => ['nullable', 'exists:product_categories,id'],
            'brand_id'         => ['nullable', 'exists:brands,id'],
            'unit_id'          => ['nullable', 'exists:units,id'],
            'barcode'          => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'cost_price'       => ['required', 'numeric', 'min:0'],
            'selling_price'    => ['required', 'numeric', 'min:0'],
            'wholesale_price'  => ['nullable', 'numeric', 'min:0'],
            'min_selling_price'=> ['nullable', 'numeric', 'min:0'],
            'tax_rate'         => ['required', 'numeric', 'in:0,7,19'],
            'reorder_level'    => ['nullable', 'integer', 'min:0'],
            'is_service'       => ['boolean'],
            'is_active'        => ['boolean'],
        ];
    }
}
