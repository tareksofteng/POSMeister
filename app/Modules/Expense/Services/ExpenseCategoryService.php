<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use Illuminate\Support\Collection;

class ExpenseCategoryService
{
    public function all(): Collection
    {
        return ExpenseCategory::withCount('expenses')
            ->orderBy('name')
            ->get();
    }

    public function activeForDropdown(): Collection
    {
        return ExpenseCategory::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function store(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data);
    }

    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function toggleStatus(ExpenseCategory $category): ExpenseCategory
    {
        $category->update(['is_active' => ! $category->is_active]);
        return $category->fresh();
    }

    public function delete(ExpenseCategory $category): void
    {
        if (Expense::where('expense_category_id', $category->id)->exists()) {
            throw new \RuntimeException('Kategorie kann nicht gelöscht werden, da ihr Ausgaben zugeordnet sind.');
        }
        $category->delete();
    }
}
