<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'branch_id'           => ['nullable', 'integer', 'exists:branches,id'],
            'title'               => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'expense_date'        => ['required', 'date'],
            'payment_method'      => ['required', 'in:cash,card,bank_transfer,cheque,other'],
            'reference_no'        => ['nullable', 'string', 'max:100'],
            'status'              => ['nullable', 'in:pending,approved,paid,rejected'],
            'attachment'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
