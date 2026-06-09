<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | AccountingDetector — Phase AB
 |--------------------------------------------------------------------------
 |
 | Watches the accounting books for control breakdowns. Four checks:
 |
 |   1. journal_imbalance        : a posted journal entry where debit ≠
 |                                 credit. This is supposed to be
 |                                 impossible (the AccountingService
 |                                 rejects unbalanced entries at the
 |                                 boundary), so any hit here is a
 |                                 critical-priority anomaly worth a
 |                                 forensic look.
 |   2. large_cash_withdrawal    : a single cashbook outflow >
 |                                 200,000 currency units today —
 |                                 fraud / governance signal.
 |   3. negative_cash_balance    : any cashbook closing balance < 0 →
 |                                 suggests a payment was recorded
 |                                 against a depleted account, which
 |                                 will trip the auditor's checklist.
 |   4. negative_bank_balance    : same but for bank accounts; usually
 |                                 means an outbound transfer was
 |                                 recorded without the inbound liquidity.
 |
 | All checks scope by workspace branch so the Dhaka manager isn't
 | alerted about Chattogram's cash book.
 */
class AccountingDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        $pushed = 0;
        foreach ($this->activeBranchIds() as $branchId) {
            $pushed += $this->journalImbalance($branchId);
            $pushed += $this->largeCashWithdrawal($branchId);
            $pushed += $this->negativeCashBalance($branchId);
            $pushed += $this->negativeBankBalance($branchId);
        }
        return $pushed;
    }

    // ── Detectors ────────────────────────────────────────────────────────

    private function journalImbalance(int $branchId): int
    {
        if (!Schema::hasTable('journal_entries') || !Schema::hasTable('journal_entry_lines')) return 0;

        // Sum debits + credits per posted entry — any mismatch beyond a
        // 1-cent rounding tolerance is a real imbalance.
        $offenders = DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jl', 'jl.entry_id', '=', 'je.id')
            ->where('je.branch_id', $branchId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('je.id, je.entry_number, SUM(jl.debit) as dr, SUM(jl.credit) as cr')
            ->groupBy('je.id', 'je.entry_number')
            ->get()
            ->filter(fn ($r) => abs(((float) $r->dr) - ((float) $r->cr)) > 0.01);

        if ($offenders->isEmpty()) return 0;

        $first = $offenders->first();
        $this->notify->push([
            'category'         => 'accounting',
            'code'             => 'accounting.journal_imbalance',
            'severity'         => 'critical',
            'urgency'          => 95,
            'title'            => $offenders->count() . " journal entry(ies) out of balance",
            'message'          => "Most recent: {$first->entry_number}. Investigate immediately — books should never drift.",
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "accounting.journal_imbalance:branch-{$branchId}",
            'cooldown_minutes' => 60,           // short cooldown — critical
            'actions'          => [
                ['label' => 'menu.journal', 'route' => 'journal-entries', 'type' => 'primary'],
            ],
            'meta'             => [
                'count'     => $offenders->count(),
                'branch_id' => $branchId,
            ],
        ]);
        return 1;
    }

    private function largeCashWithdrawal(int $branchId): int
    {
        if (!Schema::hasTable('journal_entry_lines')) return 0;

        // Large cash outflow today — typically the cash account code is
        // 1000. We sum credits today (outflow side of cash).
        $cashCode = '1000';
        if (Schema::hasTable('chart_of_accounts')) {
            $found = DB::table('chart_of_accounts')->where('account_code', $cashCode)->first();
            if (!$found) return 0;
            $accountId = $found->id;
        } else {
            return 0;
        }

        $outflow = (float) DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.entry_id')
            ->where('jl.account_id', $accountId)
            ->where('je.branch_id', $branchId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', today())
            ->sum('jl.credit');

        if ($outflow < 200000) return 0;

        $this->notify->push([
            'category'         => 'accounting',
            'code'             => 'accounting.large_cash_withdrawal',
            'severity'         => $outflow > 500000 ? 'danger' : 'warning',
            'urgency'          => 75,
            'title'            => "Large cash outflow today: " . number_format($outflow, 2),
            'message'          => 'Verify the withdrawal is authorised and entered against the correct vendor.',
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "accounting.large_cash_withdrawal:branch-{$branchId}:" . today()->format('Y-m-d'),
            'cooldown_minutes' => 1440,
            'actions'          => [
                ['label' => 'menu.cashbook', 'route' => 'cashbooks', 'type' => 'primary'],
            ],
            'meta'             => [
                'outflow'   => $outflow,
                'branch_id' => $branchId,
            ],
        ]);
        return 1;
    }

    private function negativeCashBalance(int $branchId): int
    {
        return $this->negativeAccountBalance(
            $branchId,
            accountCode: '1000',
            kind:        'cash',
            categoryCode:'accounting.negative_cash_balance',
            route:       'cashbooks',
            label:       'menu.cashbook',
        );
    }

    private function negativeBankBalance(int $branchId): int
    {
        return $this->negativeAccountBalance(
            $branchId,
            accountCode: '1100',
            kind:        'bank',
            categoryCode:'accounting.negative_bank_balance',
            route:       'bank-accounts',
            label:       'menu.bankAccounts',
        );
    }

    private function negativeAccountBalance(
        int $branchId,
        string $accountCode,
        string $kind,
        string $categoryCode,
        string $route,
        string $label,
    ): int {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('journal_entry_lines')) return 0;

        $account = DB::table('chart_of_accounts')->where('account_code', $accountCode)->first();
        if (!$account) return 0;

        $row = DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.entry_id')
            ->where('jl.account_id', $account->id)
            ->where('je.branch_id', $branchId)
            ->where('je.status', 'posted')
            ->selectRaw('COALESCE(SUM(jl.debit), 0) as dr, COALESCE(SUM(jl.credit), 0) as cr')
            ->first();

        // Asset (debit-normal) balance — DR minus CR.
        $balance = ((float) ($row->dr ?? 0)) - ((float) ($row->cr ?? 0));
        if ($balance >= 0) return 0;

        $this->notify->push([
            'category'         => 'accounting',
            'code'             => $categoryCode,
            'severity'         => 'critical',
            'urgency'          => 92,
            'title'            => ucfirst($kind) . " balance is negative: " . number_format($balance, 2),
            'message'          => "Recheck recent {$kind} entries — the closing balance should never be negative.",
            'audience_role'    => 'admin',
            'branch_id'        => $branchId,
            'dedupe_key'       => "{$categoryCode}:branch-{$branchId}",
            'cooldown_minutes' => 120,         // short — critical signal
            'actions'          => [
                ['label' => $label, 'route' => $route, 'type' => 'primary'],
            ],
            'meta'             => [
                'balance'   => $balance,
                'branch_id' => $branchId,
            ],
        ]);
        return 1;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function activeBranchIds(): array
    {
        if (!Schema::hasTable('branches')) return [];
        return DB::table('branches')->where('is_active', true)->pluck('id')->all();
    }
}
