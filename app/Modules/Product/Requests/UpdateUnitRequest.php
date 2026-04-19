<?php

namespace App\Modules\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($this->route('unit'))],
            'symbol' => ['required', 'string', 'max:10'],
        ];
    }
}
