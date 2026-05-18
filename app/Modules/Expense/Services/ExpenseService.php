<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Expense::query()
            ->with(['category:id,name,code', 'branch:id,name', 'creator:id,name']);

        $this->applyBranchScope($q);

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)
                  ->orWhere('expense_number', 'like', $term)
                  ->orWhere('reference_no', 'like', $term);
            });
        }

        if (!empty($filters['expense_category_id'])) {
            $q->where('expense_category_id', $filters['expense_category_id']);
        }

        if (!empty($filters['branch_id']) && $this->isAdmin()) {
            $q->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['payment_method'])) {
            $q->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['from'])) {
            $q->whereDate('expense_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->whereDate('expense_date', '<=', $filters['to']);
        }

        return $q->orderByDesc('expense_date')
                 ->orderByDesc('id')
                 ->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Expense
    {
        $q = Expense::with(['category', 'branch', 'creator:id,name']);
        $this->applyBranchScope($q);
        return $q->findOrFail($id);
    }

    public function store(array $data, ?UploadedFile $attachment = null): Expense
    {
        $data['expense_number'] = $this->generateNumber();

        if (empty($data['branch_id'])) {
            $data['branch_id'] = Auth::user()?->branch_id;
        }

        if ($attachment) {
            $data['attachment'] = $this->saveAttachment($attachment);
        }

        $expense = Expense::create($data);
        return $this->find($expense->id);
    }

    public function update(Expense $expense, array $data, ?UploadedFile $attachment = null): Expense
    {
        unset($data['expense_number']);

        if (!$this->isAdmin()) {
            unset($data['branch_id']);
        }

        if ($attachment) {
            if ($expense->attachment) {
                Storage::disk('public')->delete($expense->attachment);
            }
            $data['attachment'] = $this->saveAttachment($attachment);
        }

        $expense->update($data);
        return $this->find($expense->id);
    }

    public function destroy(Expense $expense): void
    {
        if ($expense->attachment) {
            Storage::disk('public')->delete($expense->attachment);
        }
        $expense->delete();
    }

    public function summary(array $filters = []): array
    {
        $q = Expense::query();
        $this->applyBranchScope($q);

        if (!empty($filters['from'])) $q->whereDate('expense_date', '>=', $filters['from']);
        if (!empty($filters['to']))   $q->whereDate('expense_date', '<=', $filters['to']);
        if (!empty($filters['expense_category_id'])) $q->where('expense_category_id', $filters['expense_category_id']);
        if (!empty($filters['branch_id']) && $this->isAdmin()) $q->where('branch_id', $filters['branch_id']);

        $byStatus = (clone $q)->selectRaw('status, count(*) as c, coalesce(sum(amount), 0) as total')
            ->groupBy('status')->get();

        $totals = [
            'total_count' => (int) $byStatus->sum('c'),
            'total_amount'=> round((float) $byStatus->sum('total'), 2),
            'pending'     => 0.0,
            'approved'    => 0.0,
            'paid'        => 0.0,
            'rejected'    => 0.0,
        ];
        foreach ($byStatus as $row) {
            $totals[$row->status] = round((float) $row->total, 2);
        }

        return $totals;
    }

    private function saveAttachment(UploadedFile $file): string
    {
        return $file->store('expenses', 'public');
    }

    private function generateNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = "EXP-{$year}-";
        $last   = Expense::withTrashed()
            ->where('expense_number', 'like', $prefix . '%')
            ->max('expense_number');
        $next = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function applyBranchScope($query): void
    {
        $user = Auth::user();
        if (!$user || $this->isAdmin()) {
            return;
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
