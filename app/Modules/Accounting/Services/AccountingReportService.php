<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Branch\Services\BranchContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregations over the journal. All queries push down to
 * journal_entry_lines with branch + date scoping so big ledgers stay fast.
 */
class AccountingReportService
{
    /**
     * General ledger for one account: opening + dated rows with running balance.
     */
    public function ledger(int $accountId, string $from, string $to, ?int $branchId = null): array
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $opening = $this->balanceBefore($accountId, $from, $branchId);

        $rows = JournalEntryLine::query()
            ->with(['entry:id,entry_number,reference_type,reference_number,narration,status'])
            ->where('account_id', $accountId)
            ->whereBetween('entry_date', [$from, $to])
            ->tap(fn($q) => $this->applyBranchScope($q, $branchId))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $debitTotal = 0;
        $creditTotal = 0;
        $out = [];

        foreach ($rows as $r) {
            $debit  = (float) $r->debit;
            $credit = (float) $r->credit;
            $debitTotal  += $debit;
            $creditTotal += $credit;

            $running += $account->isDebitNormal()
                ? $debit - $credit
                : $credit - $debit;

            $out[] = [
                'id'               => $r->id,
                'entry_date'       => $r->entry_date->toDateString(),
                'entry_number'     => $r->entry?->entry_number,
                'reference_type'   => $r->entry?->reference_type,
                'reference_number' => $r->entry?->reference_number,
                'narration'        => $r->narration ?: $r->entry?->narration,
                'debit'            => round($debit, 2),
                'credit'           => round($credit, 2),
                'running_balance'  => round($running, 2),
            ];
        }

        return [
            'account' => [
                'id'             => $account->id,
                'code'           => $account->account_code,
                'name'           => $account->account_name,
                'type'           => $account->account_type,
                'normal_balance' => $account->normal_balance,
            ],
            'period'   => ['from' => $from, 'to' => $to],
            'opening'  => round($opening, 2),
            'closing'  => round($running, 2),
            'debit_total'  => round($debitTotal, 2),
            'credit_total' => round($creditTotal, 2),
            'lines'    => $out,
        ];
    }

    /**
     * Trial balance: every account with non-zero balance in the period.
     * Returned columns: opening_balance, period_debit, period_credit, closing_balance.
     */
    public function trialBalance(string $asOf, ?int $branchId = null): array
    {
        $accounts = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $a) {
            $opening = $this->balanceBefore($a->id, Carbon::parse($asOf)->startOfYear()->toDateString(), $branchId);

            $totals = JournalEntryLine::query()
                ->where('account_id', $a->id)
                ->whereBetween('entry_date', [
                    Carbon::parse($asOf)->startOfYear()->toDateString(),
                    $asOf,
                ])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
                ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
                ->first();

            $periodD = (float) $totals->d;
            $periodC = (float) $totals->c;
            $closing = $opening + ($a->isDebitNormal() ? $periodD - $periodC : $periodC - $periodD);

            if (abs($opening) < 0.01 && abs($periodD) < 0.01 && abs($periodC) < 0.01) {
                continue;
            }

            $debitSide  = $a->isDebitNormal()  ? max($closing, 0) : 0;
            $creditSide = $a->isCreditNormal() ? max($closing, 0) : 0;

            $totalDebit  += $debitSide;
            $totalCredit += $creditSide;

            $rows[] = [
                'account_id'       => $a->id,
                'account_code'     => $a->account_code,
                'account_name'     => $a->account_name,
                'account_type'     => $a->account_type,
                'opening_balance'  => round($opening, 2),
                'period_debit'     => round($periodD, 2),
                'period_credit'    => round($periodC, 2),
                'closing_balance'  => round($closing, 2),
                'debit_side'       => round($debitSide, 2),
                'credit_side'      => round($creditSide, 2),
            ];
        }

        return [
            'as_of'       => $asOf,
            'rows'        => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit'=> round($totalCredit, 2),
            'balanced'    => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    /**
     * Profit & Loss: revenue minus expenses for a date range.
     */
    public function profitLoss(string $from, string $to, ?int $branchId = null): array
    {
        $revenueRows = $this->groupByType('revenue', $from, $to, $branchId);
        $expenseRows = $this->groupByType('expense', $from, $to, $branchId);

        $revenueTotal = array_sum(array_column($revenueRows, 'amount'));
        $expenseTotal = array_sum(array_column($expenseRows, 'amount'));

        return [
            'period'        => ['from' => $from, 'to' => $to],
            'revenue'       => $revenueRows,
            'expense'       => $expenseRows,
            'revenue_total' => round($revenueTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'net_profit'    => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    /**
     * Balance sheet snapshot at a given date.
     */
    public function balanceSheet(string $asOf, ?int $branchId = null): array
    {
        $assets      = $this->snapshotByType('asset',     $asOf, $branchId);
        $liabilities = $this->snapshotByType('liability', $asOf, $branchId);
        $equity      = $this->snapshotByType('equity',    $asOf, $branchId);

        $assetTotal     = array_sum(array_column($assets,      'amount'));
        $liabilityTotal = array_sum(array_column($liabilities, 'amount'));
        $equityTotal    = array_sum(array_column($equity,      'amount'));

        // Retained earnings flow into equity (revenue - expense for current year)
        $ytdProfit = $this->yearProfit($asOf, $branchId);

        return [
            'as_of'           => $asOf,
            'assets'          => $assets,
            'liabilities'     => $liabilities,
            'equity'          => $equity,
            'asset_total'     => round($assetTotal, 2),
            'liability_total' => round($liabilityTotal, 2),
            'equity_total'    => round($equityTotal, 2),
            'ytd_profit'      => round($ytdProfit, 2),
            'balanced'        => abs($assetTotal - ($liabilityTotal + $equityTotal + $ytdProfit)) < 0.5,
        ];
    }

    /**
     * Cashbook for a single account on a date range. Returns daily totals
     * plus opening + closing balance.
     */
    public function cashbook(int $accountId, string $from, string $to, ?int $branchId = null): array
    {
        $account = ChartOfAccount::findOrFail($accountId);
        $opening = $this->balanceBefore($accountId, $from, $branchId);

        $rows = JournalEntryLine::query()
            ->where('account_id', $accountId)
            ->whereBetween('entry_date', [$from, $to])
            ->tap(fn($q) => $this->applyBranchScope($q, $branchId))
            ->selectRaw('entry_date, SUM(debit) as cash_in, SUM(credit) as cash_out')
            ->groupBy('entry_date')
            ->orderBy('entry_date')
            ->get();

        $running = $opening;
        $days = [];
        foreach ($rows as $r) {
            $in  = (float) $r->cash_in;
            $out = (float) $r->cash_out;
            $running += $in - $out;
            $days[] = [
                'date'     => Carbon::parse($r->entry_date)->toDateString(),
                'cash_in'  => round($in, 2),
                'cash_out' => round($out, 2),
                'closing'  => round($running, 2),
            ];
        }

        return [
            'account' => [
                'id'   => $account->id,
                'code' => $account->account_code,
                'name' => $account->account_name,
            ],
            'period'  => ['from' => $from, 'to' => $to],
            'opening' => round($opening, 2),
            'closing' => round($running, 2),
            'days'    => $days,
        ];
    }

    /**
     * Dashboard snapshot used by the accounting overview page.
     */
    public function dashboard(?string $asOf = null, ?int $branchId = null): array
    {
        $asOf = $asOf ?: Carbon::today()->toDateString();
        $monthStart = Carbon::parse($asOf)->startOfMonth()->toDateString();

        return [
            'as_of'         => $asOf,
            'cash_balance'  => round($this->accountBalance('1000', $asOf, $branchId), 2),
            'bank_balance'  => round($this->accountBalance('1100', $asOf, $branchId), 2),
            'receivables'   => round($this->accountBalance('1200', $asOf, $branchId), 2),
            'payables'      => round($this->accountBalance('2000', $asOf, $branchId), 2),
            'monthly_expense' => round($this->totalsByType('expense', $monthStart, $asOf, $branchId), 2),
            'monthly_revenue' => round($this->totalsByType('revenue', $monthStart, $asOf, $branchId), 2),
            'ytd_profit'    => round($this->yearProfit($asOf, $branchId), 2),
            'recent_entries'=> $this->recentEntries($branchId, 8),
        ];
    }

    // --- internals -----------------------------------------------------------

    public function balanceBefore(int $accountId, string $date, ?int $branchId): float
    {
        $account = ChartOfAccount::find($accountId);
        if (!$account) return 0.0;

        $row = JournalEntryLine::query()
            ->where('account_id', $accountId)
            ->where('entry_date', '<', $date)
            ->tap(fn($q) => $this->applyBranchScope($q, $branchId))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $d = (float) $row->d;
        $c = (float) $row->c;
        return $account->isDebitNormal() ? $d - $c : $c - $d;
    }

    public function accountBalance(string $accountCode, string $asOf, ?int $branchId): float
    {
        $account = ChartOfAccount::where('account_code', $accountCode)->first();
        if (!$account) return 0.0;

        $row = JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->where('entry_date', '<=', $asOf)
            ->tap(fn($q) => $this->applyBranchScope($q, $branchId))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $d = (float) $row->d;
        $c = (float) $row->c;
        return $account->isDebitNormal() ? $d - $c : $c - $d;
    }

    private function groupByType(string $type, string $from, string $to, ?int $branchId): array
    {
        $rows = JournalEntryLine::query()
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.account_type', $type)
            ->whereBetween('journal_entry_lines.entry_date', [$from, $to])
            ->when($branchId, fn($q) => $q->where('journal_entry_lines.branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('journal_entry_lines.branch_id', Auth::user()->branch_id))
            ->selectRaw('
                chart_of_accounts.id as account_id,
                chart_of_accounts.account_code,
                chart_of_accounts.account_name,
                chart_of_accounts.normal_balance,
                COALESCE(SUM(journal_entry_lines.debit), 0)  as d,
                COALESCE(SUM(journal_entry_lines.credit), 0) as c
            ')
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.normal_balance',
            )
            ->orderBy('chart_of_accounts.account_code')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $d = (float) $r->d;
            $c = (float) $r->c;
            $amount = $r->normal_balance === 'debit' ? $d - $c : $c - $d;
            if (abs($amount) < 0.01) continue;
            $out[] = [
                'account_id'   => (int) $r->account_id,
                'account_code' => $r->account_code,
                'account_name' => $r->account_name,
                'amount'       => round($amount, 2),
            ];
        }
        return $out;
    }

    private function snapshotByType(string $type, string $asOf, ?int $branchId): array
    {
        $rows = JournalEntryLine::query()
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.account_type', $type)
            ->where('journal_entry_lines.entry_date', '<=', $asOf)
            ->when($branchId, fn($q) => $q->where('journal_entry_lines.branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('journal_entry_lines.branch_id', Auth::user()->branch_id))
            ->selectRaw('
                chart_of_accounts.id as account_id,
                chart_of_accounts.account_code,
                chart_of_accounts.account_name,
                chart_of_accounts.normal_balance,
                COALESCE(SUM(journal_entry_lines.debit), 0)  as d,
                COALESCE(SUM(journal_entry_lines.credit), 0) as c
            ')
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.normal_balance',
            )
            ->orderBy('chart_of_accounts.account_code')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $d = (float) $r->d;
            $c = (float) $r->c;
            $amount = $r->normal_balance === 'debit' ? $d - $c : $c - $d;
            if (abs($amount) < 0.01) continue;
            $out[] = [
                'account_id'   => (int) $r->account_id,
                'account_code' => $r->account_code,
                'account_name' => $r->account_name,
                'amount'       => round($amount, 2),
            ];
        }
        return $out;
    }

    private function totalsByType(string $type, string $from, string $to, ?int $branchId): float
    {
        $totals = $this->groupByType($type, $from, $to, $branchId);
        return array_sum(array_column($totals, 'amount'));
    }

    private function yearProfit(string $asOf, ?int $branchId): float
    {
        $start = Carbon::parse($asOf)->startOfYear()->toDateString();
        $rev = $this->totalsByType('revenue', $start, $asOf, $branchId);
        $exp = $this->totalsByType('expense', $start, $asOf, $branchId);
        return $rev - $exp;
    }

    private function recentEntries(?int $branchId, int $limit): array
    {
        $q = DB::table('journal_entries')
            ->where('status', 'posted')
            ->when($branchId, fn($qq) => $qq->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($qq) => $qq->where('branch_id', Auth::user()->branch_id))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit);

        return $q->get(['id', 'entry_number', 'entry_date', 'reference_type', 'reference_number', 'narration', 'total_debit'])
            ->map(fn($r) => [
                'id'             => $r->id,
                'entry_number'   => $r->entry_number,
                'entry_date'     => $r->entry_date,
                'reference_type' => $r->reference_type,
                'reference_number' => $r->reference_number,
                'narration'      => $r->narration,
                'amount'         => round((float) $r->total_debit, 2),
            ])
            ->all();
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    /**
     * Branch scope used by every report query. Combines the optional
     * explicit drill-down (`$explicit`, e.g. "show me Dhaka only" picked
     * from a report filter) with the active Topbar workspace context so
     * an admin browsing in Chattogram can no longer see Dhaka ledgers
     * via a missing-filter call. Order of precedence:
     *
     *   1. $explicit (user-driven drill-down)            → strictly that branch
     *   2. BranchContextService::current()              → strictly that branch
     *   3. Main Branch / All Branches super workspace   → no scope
     */
    private function applyBranchScope($q, ?int $explicit): void
    {
        if ($explicit) {
            $q->where('branch_id', $explicit);
            return;
        }
        app(BranchContextService::class)->scopeQuery($q);
    }
}
