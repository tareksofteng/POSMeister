<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name'        => ['required', 'string', 'max:120', "unique:expense_categories,name,{$id},id,deleted_at,NULL"],
            'code'        => ['nullable', 'string', 'max:30',  "unique:expense_categories,code,{$id},id,deleted_at,NULL"],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
