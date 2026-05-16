<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'        => ['required', 'string', 'max:60'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id'    => ['nullable', 'integer', 'exists:branches,id'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
