<?php

namespace App\Modules\Serials\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachSaleSerialsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'sale_id'      => ['required', 'integer', 'exists:sales,id'],
            'sale_item_id' => ['nullable', 'integer'],
            'customer_id'  => ['nullable', 'integer', 'exists:customers,id'],
            'branch_id'    => ['nullable', 'integer', 'exists:branches,id'],
            'serial_ids'   => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['required', 'integer', 'exists:product_serials,id'],
        ];
    }
}
