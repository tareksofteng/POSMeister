<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The bookkeeping engine. All journal creation flows through here so that
 * debit = credit is enforced in one place. Callers should NOT touch
 * journal_entries / journal_entry_lines directly.
 */
class AccountingService
{
    /**
     * Create and immediately post a balanced journal entry.
     *
     * $lines is a list of:
     *   ['account_code' => string OR 'account_id' => int, 'debit' => float, 'credit' => float, 'narration' => string|null]
     *
     * Exactly one of debit/credit must be non-zero per line.
     */
    public function createJournalEntry(array $payload, array $lines): JournalEntry
    {
        $entryDate = isset($payload['entry_date'])
            ? Carbon::parse($payload['entry_date'])->toDateString()
            : Carbon::today()->toDateString();

        $branchId = $payload['branch_id'] ?? Auth::user()?->branch_id;
        $status   = $payload['status'] ?? 'posted';

        return DB::transaction(function () use ($payload, $lines, $entryDate, $branchId, $status) {

            $resolved = $this->resolveLines($lines, $entryDate, $branchId);
            $totals   = $this->sumTotals($resolved);

            $this->assertBalanced($totals['debit'], $totals['credit']);

            $entry = JournalEntry::create([
                'entry_number'     => $payload['entry_number'] ?? $this->nextEntryNumber($entryDate),
                'entry_date'       => $entryDate,
                'branch_id'        => $branchId,
                'reference_type'   => $payload['reference_type']   ?? null,
                'reference_id'     => $payload['reference_id']     ?? null,
                'reference_number' => $payload['reference_number'] ?? null,
                'narration'        => $payload['narration']        ?? null,
                'total_debit'      => $totals['debit'],
                'total_credit'     => $totals['credit'],
                'status'           => $status,
                'posted_at'        => $status === 'posted' ? now() : null,
                'posted_by'        => $status === 'posted' ? Auth::id() : null,
            ]);

            $lineNo = 1;
            foreach ($resolved as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'branch_id'        => $branchId,
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'entry_date'       => $entryDate,
                    'narration'        => $line['narration'] ?? null,
                    'line_no'          => $lineNo++,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Reverse a posted entry by creating a mirror entry with debits/credits swapped.
     * Original keeps its lines; the new entry is linked via reversed_by_entry_id.
     */
    public function reverse(JournalEntry $entry, ?string $narration = null): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new RuntimeException('Only posted entries can be reversed.');
        }

        return DB::transaction(function () use ($entry, $narration) {
            $reverseLines = $entry->lines->map(fn($l) => [
                'account_id' => $l->account_id,
                'debit'      => (float) $l->credit,
                'credit'     => (float) $l->debit,
                'narration'  => $l->narration,
            ])->all();

            $reverse = $this->createJournalEntry([
                'entry_date'     => Carbon::today()->toDateString(),
                'branch_id'      => $entry->branch_id,
                'reference_type' => 'reversal',
                'reference_id'   => $entry->id,
                'reference_number' => $entry->entry_number,
                'narration'      => $narration ?: ('Storno für ' . $entry->entry_number),
                'status'         => 'posted',
            ], $reverseLines);

            $entry->update([
                'status'               => 'reversed',
                'reversed_by_entry_id' => $reverse->id,
            ]);

            return $reverse;
        });
    }

    public function validateBalanced(array $lines): bool
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $l) {
            $debit  += (float) ($l['debit']  ?? 0);
            $credit += (float) ($l['credit'] ?? 0);
        }
        return abs($debit - $credit) < 0.01;
    }

    public function accountByCode(string $code): ChartOfAccount
    {
        $a = ChartOfAccount::where('account_code', $code)->first();
        if (!$a) {
            throw new RuntimeException("Account code {$code} not found in chart of accounts.");
        }
        return $a;
    }

    public function nextEntryNumber(?string $date = null): string
    {
        $year = $date ? Carbon::parse($date)->year : Carbon::now()->year;
        $last = JournalEntry::where('entry_number', 'like', "JE-{$year}-%")
            ->orderByDesc('id')
            ->value('entry_number');

        $seq = 1;
        if ($last && preg_match('/JE-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('JE-%d-%05d', $year, $seq);
    }

    // --- internal helpers ----------------------------------------------------

    private function resolveLines(array $lines, string $entryDate, ?int $branchId): array
    {
        $out = [];
        foreach ($lines as $i => $l) {
            $debit  = round((float) ($l['debit']  ?? 0), 2);
            $credit = round((float) ($l['credit'] ?? 0), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue; // silently drop zero lines so callers can include conditional rows
            }
            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException("Line {$i} has both debit and credit. Use two lines instead.");
            }

            if (isset($l['account_id'])) {
                $accountId = $l['account_id'];
            } elseif (isset($l['account_code'])) {
                $accountId = $this->accountByCode($l['account_code'])->id;
            } else {
                throw new RuntimeException("Line {$i} missing account_id and account_code.");
            }

            $out[] = [
                'account_id' => $accountId,
                'debit'      => $debit,
                'credit'     => $credit,
                'narration'  => $l['narration'] ?? null,
            ];
        }
        return $out;
    }

    private function sumTotals(array $lines): array
    {
        $d = 0;
        $c = 0;
        foreach ($lines as $l) {
            $d += $l['debit'];
            $c += $l['credit'];
        }
        return ['debit' => round($d, 2), 'credit' => round($c, 2)];
    }

    private function assertBalanced(float $debit, float $credit): void
    {
        if (abs($debit - $credit) >= 0.01) {
            throw new RuntimeException(
                "Journal entry is not balanced. Debit={$debit}, Credit={$credit}."
            );
        }
        if ($debit <= 0) {
            throw new RuntimeException('Journal entry must have at least one non-zero line.');
        }
    }
}
