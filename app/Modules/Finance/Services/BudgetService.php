<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Budget::query()
            ->with(['branch:id,name', 'items'])
            ->withCount('items');

        $this->applyBranchScope($q);

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['fiscal_year'])) {
            $q->where('fiscal_year', $filters['fiscal_year']);
        }
        if (!empty($filters['branch_id']) && $this->isAdmin()) {
            $q->where('branch_id', $filters['branch_id']);
        }

        return $q->orderByDesc('fiscal_year')
                 ->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Budget
    {
        $q = Budget::with([
            'branch:id,name',
            'items.category:id,name,code',
            'creator:id,name',
        ]);
        $this->applyBranchScope($q);
        return $q->findOrFail($id);
    }

    public function store(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            $this->validateAllocations($items, $data['total_budget']);
            $this->validateSingleActive($data, null);

            if (empty($data['branch_id'])) {
                $data['branch_id'] = Auth::user()?->branch_id;
            }

            $budget = Budget::create(collect($data)->except('items')->toArray());

            foreach ($items as $item) {
                $budget->items()->create([
                    'expense_category_id' => $item['expense_category_id'],
                    'allocated_amount'    => $item['allocated_amount'],
                ]);
            }

            return $this->find($budget->id);
        });
    }

    public function update(Budget $budget, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data) {
            $items = $data['items'] ?? [];
            $this->validateAllocations($items, $data['total_budget'] ?? $budget->total_budget);
            $this->validateSingleActive($data, $budget->id);

            if (!$this->isAdmin()) {
                unset($data['branch_id']);
            }

            $budget->update(collect($data)->except('items')->toArray());

            if (array_key_exists('items', $data)) {
                $budget->items()->delete();
                foreach ($items as $item) {
                    $budget->items()->create([
                        'expense_category_id' => $item['expense_category_id'],
                        'allocated_amount'    => $item['allocated_amount'],
                    ]);
                }
            }

            return $this->find($budget->id);
        });
    }

    public function setStatus(Budget $budget, string $status): Budget
    {
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            throw new \RuntimeException('Ungültiger Status.');
        }

        if ($status === 'active') {
            $this->validateSingleActive([
                'branch_id'   => $budget->branch_id,
                'fiscal_year' => $budget->fiscal_year,
                'status'      => 'active',
            ], $budget->id);
        }

        $budget->update(['status' => $status]);
        return $budget->fresh();
    }

    public function duplicate(Budget $source, int $newFiscalYear): Budget
    {
        return DB::transaction(function () use ($source, $newFiscalYear) {
            $copy = $source->replicate(['created_by', 'updated_by']);
            $copy->title       = $source->title . ' (' . $newFiscalYear . ')';
            $copy->fiscal_year = $newFiscalYear;
            $copy->start_date  = $source->start_date->copy()->year($newFiscalYear);
            $copy->end_date    = $source->end_date->copy()->year($newFiscalYear);
            $copy->status      = 'draft';
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create([
                    'expense_category_id' => $item->expense_category_id,
                    'allocated_amount'    => $item->allocated_amount,
                ]);
            }

            return $this->find($copy->id);
        });
    }

    public function destroy(Budget $budget): void
    {
        if ($budget->status === 'active') {
            throw new \RuntimeException('Aktive Budgets können nicht gelöscht werden. Bitte zuerst archivieren.');
        }
        $budget->delete();
    }

    private function validateAllocations(array $items, $total): void
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += (float) ($item['allocated_amount'] ?? 0);
        }
        if ($sum > (float) $total + 0.001) {
            throw new \RuntimeException(
                'Die Summe der Zuweisungen übersteigt das Gesamtbudget.'
            );
        }
    }

    private function validateSingleActive(array $data, ?int $ignoreId): void
    {
        if (($data['status'] ?? null) !== 'active') {
            return;
        }
        $q = Budget::where('status', 'active')
            ->where('fiscal_year', $data['fiscal_year'] ?? null)
            ->where('branch_id', $data['branch_id'] ?? null);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw new \RuntimeException(
                'Für diese Filiale und dieses Geschäftsjahr existiert bereits ein aktives Budget.'
            );
        }
    }

    private function applyBranchScope($query): void
    {
        $user = Auth::user();
        if ($this->isAdmin()) {
            return;
        }
        if ($user?->branch_id) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                  ->orWhereNull('branch_id');
            });
        }
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
