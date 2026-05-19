<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                       => ['required', 'string', 'max:150'],
            'fiscal_year'                 => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date'                  => ['required', 'date'],
            'end_date'                    => ['required', 'date', 'after_or_equal:start_date'],
            'branch_id'                   => ['nullable', 'integer', 'exists:branches,id'],
            'total_budget'                => ['required', 'numeric', 'min:0'],
            'warning_threshold_percent'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'status'                      => ['nullable', 'in:draft,active,archived'],
            'notes'                       => ['nullable', 'string'],
            'items'                       => ['nullable', 'array'],
            'items.*.expense_category_id' => ['required_with:items', 'integer', 'exists:expense_categories,id'],
            'items.*.allocated_amount'    => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
