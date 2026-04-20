<?php

namespace App\Modules\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already guarded by role:admin middleware
    }

    public function rules(): array
    {
        return [
            'company_name'    => ['required', 'string', 'max:120'],
            'address'         => ['nullable', 'string', 'max:500'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:120'],
            'currency_code'   => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'vat_default'     => ['required', 'numeric', 'in:0,7,19'],
            'invoice_prefix'  => ['required', 'string', 'max:20'],
            'invoice_footer'  => ['nullable', 'string', 'max:1000'],
            'date_format'     => ['required', 'string', 'in:d.m.Y,m/d/Y,Y-m-d'],
        ];
    }
}
