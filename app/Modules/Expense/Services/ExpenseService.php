<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        if (array_key_exists('is_recurring', $filters) && $filters['is_recurring'] !== '' && $filters['is_recurring'] !== null) {
            $q->where('is_recurring', (bool) $filters['is_recurring']);
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
        $q = Expense::with([
            'category', 'branch', 'creator:id,name',
            'approver:id,name', 'rejecter:id,name', 'payer:id,name',
        ]);
        $this->applyBranchScope($q);
        return $q->findOrFail($id);
    }

    public function store(array $data, ?UploadedFile $attachment = null): Expense
    {
        $data['expense_number'] = $this->generateNumber();
        $data['status'] = 'pending';

        // Workspace context binds on writes — payload branch_id is
        // accepted only when admin is doing an explicit override and the
        // legacy `branch_id` filter survived in $data. Default flow uses
        // the Topbar workspace; cashier home branch is the last resort.
        $data['branch_id'] = $data['branch_id']
            ?? app(\App\Modules\Branch\Services\BranchContextService::class)->current()
            ?? Auth::user()?->branch_id;

        if ($attachment) {
            $data['attachment'] = $this->saveAttachment($attachment);
        }

        $expense = Expense::create($data);
        $this->log($expense, 'created');

        return $this->find($expense->id);
    }

    public function update(Expense $expense, array $data, ?UploadedFile $attachment = null): Expense
    {
        if ($expense->isPaid()) {
            throw new \RuntimeException(__('errors.expenses.paid_not_editable'));
        }

        unset($data['expense_number'], $data['status']);

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
        $this->log($expense, 'updated');

        return $this->find($expense->id);
    }

    public function destroy(Expense $expense): void
    {
        if ($expense->isPaid()) {
            throw new \RuntimeException(__('errors.expenses.paid_not_deletable'));
        }
        if ($expense->attachment) {
            Storage::disk('public')->delete($expense->attachment);
        }
        $this->log($expense, 'deleted');
        $expense->delete();
    }

    // ---- Workflow transitions --------------------------------------------

    public function approve(Expense $expense, ?string $notes = null): Expense
    {
        if (!$expense->isPending()) {
            throw new \RuntimeException(__('errors.expenses.only_open_approvable'));
        }

        $expense->update([
            'status'       => 'approved',
            'approved_by'  => Auth::id(),
            'approved_at'  => now(),
            'rejected_by'  => null,
            'rejected_at'  => null,
            'rejection_reason' => null,
        ]);
        $this->log($expense, 'approved', $notes);

        return $this->find($expense->id);
    }

    public function reject(Expense $expense, string $reason): Expense
    {
        if ($expense->isPaid()) {
            throw new \RuntimeException(__('errors.expenses.paid_not_rejectable'));
        }

        $expense->update([
            'status'           => 'rejected',
            'rejected_by'      => Auth::id(),
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
            'approved_by'      => null,
            'approved_at'      => null,
        ]);
        $this->log($expense, 'rejected', $reason);

        return $this->find($expense->id);
    }

    public function markPaid(Expense $expense, array $data = []): Expense
    {
        if ($expense->isRejected()) {
            throw new \RuntimeException(__('errors.expenses.rejected_not_payable'));
        }
        if ($expense->isPaid()) {
            throw new \RuntimeException(__('errors.expenses.already_paid'));
        }

        $expense->update([
            'status'           => 'paid',
            'paid_by'          => Auth::id(),
            'paid_at'          => $data['paid_at'] ?? now(),
            'payment_method'   => $data['payment_method'] ?? $expense->payment_method,
            'reference_no'     => $data['reference_no'] ?? $expense->reference_no,
        ]);
        $this->log($expense, 'paid', $data['notes'] ?? null);

        return $this->find($expense->id);
    }

    public function reopen(Expense $expense, ?string $notes = null): Expense
    {
        if ($expense->isPaid()) {
            throw new \RuntimeException(__('errors.expenses.paid_not_reopenable'));
        }

        $expense->update([
            'status'           => 'pending',
            'approved_by'      => null,
            'approved_at'      => null,
            'rejected_by'      => null,
            'rejected_at'      => null,
            'rejection_reason' => null,
        ]);
        $this->log($expense, 'reopened', $notes);

        return $this->find($expense->id);
    }

    // ---- Summary + audit -------------------------------------------------

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

    public function auditLog(Expense $expense)
    {
        return $expense->auditLogs()->with('user:id,name')->get()->map(function ($log) {
            return [
                'id'         => $log->id,
                'action'     => $log->action,
                'notes'      => $log->notes,
                'user_name'  => $log->user?->name ?? '—',
                'created_at' => $log->created_at?->format('Y-m-d H:i'),
            ];
        });
    }

    /**
     * Generate a CSV stream. `format=datev` switches column layout to the
     * common DATEV import shape (date, account, contra account, amount,
     * description) so the file can be imported into the German accounting tool.
     */
    public function exportCsv(array $filters = [], string $format = 'standard'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'expenses-' . now()->format('Y-m-d') . ($format === 'datev' ? '-datev' : '') . '.csv';

        return response()->stream(function () use ($filters, $format) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 cleanly
            fwrite($out, "\xEF\xBB\xBF");

            $rows = $this->buildExportRows($filters, $format);
            foreach ($rows as $row) {
                fputcsv($out, $row, $format === 'datev' ? ';' : ',');
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildExportRows(array $filters, string $format): array
    {
        $q = Expense::with(['category:id,name,code', 'branch:id,name', 'creator:id,name']);
        $this->applyBranchScope($q);

        if (!empty($filters['from'])) $q->whereDate('expense_date', '>=', $filters['from']);
        if (!empty($filters['to']))   $q->whereDate('expense_date', '<=', $filters['to']);
        if (!empty($filters['expense_category_id'])) $q->where('expense_category_id', $filters['expense_category_id']);
        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['branch_id']) && $this->isAdmin()) $q->where('branch_id', $filters['branch_id']);

        $expenses = $q->orderBy('expense_date')->get();

        if ($format === 'datev') {
            $rows = [['Umsatz', 'Soll/Haben', 'WKZ', 'Kurs', 'Konto', 'Gegenkonto', 'BU', 'Datum', 'Beleg', 'Buchungstext']];
            foreach ($expenses as $e) {
                $rows[] = [
                    number_format((float) $e->amount, 2, ',', ''),
                    'S',
                    'EUR',
                    '',
                    $e->category?->code ?: '4980',  // sample expense account
                    '1200',                          // bank contra account
                    '',
                    $e->expense_date->format('d.m.Y'),
                    $e->expense_number,
                    $this->shortenForDatev($e->title),
                ];
            }
            return $rows;
        }

        $rows = [[
            'Nummer', 'Datum', 'Bezeichnung', 'Kategorie', 'Filiale',
            'Betrag', 'Zahlungsart', 'Referenz', 'Status',
            'Erstellt von', 'Erstellt am',
        ]];
        foreach ($expenses as $e) {
            $rows[] = [
                $e->expense_number,
                $e->expense_date->format('Y-m-d'),
                $e->title,
                $e->category?->name ?? '',
                $e->branch?->name ?? '',
                number_format((float) $e->amount, 2, '.', ''),
                $e->payment_method,
                $e->reference_no ?? '',
                $e->status,
                $e->creator?->name ?? '',
                $e->created_at?->format('Y-m-d H:i'),
            ];
        }
        return $rows;
    }

    private function shortenForDatev(?string $text): string
    {
        // DATEV Buchungstext is limited to 60 chars
        return mb_substr((string) $text, 0, 60);
    }

    // ---- Internals -------------------------------------------------------

    private function log(Expense $expense, string $action, ?string $notes = null): void
    {
        ExpenseAuditLog::create([
            'expense_id' => $expense->id,
            'user_id'    => Auth::id(),
            'action'     => $action,
            'notes'      => $notes,
            'created_at' => now(),
        ]);
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
        // Topbar workspace context is now the source of truth — admin in
        // Chattogram workspace must NOT see Dhaka expenses (the old code
        // returned early for every admin, leaking everything).
        // BranchContextService::scopeQuery does the right thing for
        // Main Branch / All Branches (no filter) and specific branches
        // (where branch_id = current).
        app(\App\Modules\Branch\Services\BranchContextService::class)->scopeQuery($query);
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
