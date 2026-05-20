<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\CustomerWallet;
use App\Modules\CRM\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Customer wallet operations. All movements funnel through `post()`
 * so that balance updates + the ledger row are atomic. Transactions
 * are immutable; corrections come via reversing entries.
 */
class WalletService
{
    public function wallet(int $customerId, bool $lock = false): CustomerWallet
    {
        $q = CustomerWallet::where('customer_id', $customerId);
        if ($lock) $q->lockForUpdate();

        $wallet = $q->first();
        if (!$wallet) {
            $wallet = CustomerWallet::create([
                'customer_id' => $customerId,
                'balance'     => 0,
            ]);
            if ($lock) {
                $wallet = CustomerWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }
        }
        return $wallet;
    }

    public function credit(int $customerId, float $amount, string $type, string $note, ?int $branchId = null, ?string $referenceType = null, ?int $referenceId = null, ?string $referenceNumber = null): WalletTransaction
    {
        $this->assertCreditType($type);
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }
        return $this->post($customerId, $type, $amount, $note, $branchId, $referenceType, $referenceId, $referenceNumber);
    }

    public function debit(int $customerId, float $amount, string $note, ?int $branchId = null, ?string $referenceType = null, ?int $referenceId = null, ?string $referenceNumber = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }
        return $this->post($customerId, 'debit', -$amount, $note, $branchId, $referenceType, $referenceId, $referenceNumber);
    }

    public function adjust(int $customerId, float $signedAmount, string $note, ?int $branchId = null): WalletTransaction
    {
        if (abs($signedAmount) < 0.005) {
            throw new RuntimeException('Adjustment cannot be zero.');
        }
        return $this->post($customerId, 'adjust', $signedAmount, $note ?: 'Manuelle Korrektur', $branchId);
    }

    /**
     * Summary for the POS terminal / customer profile.
     */
    public function summary(int $customerId): array
    {
        $wallet = $this->wallet($customerId);
        return [
            'customer_id'       => $wallet->customer_id,
            'balance'           => (float) $wallet->balance,
            'lifetime_credited' => (float) $wallet->lifetime_credited,
            'lifetime_debited'  => (float) $wallet->lifetime_debited,
            'allow_negative'    => (bool) $wallet->allow_negative,
            'currency'          => $wallet->currency,
        ];
    }

    private function post(int $customerId, string $type, float $signedAmount, string $note, ?int $branchId, ?string $referenceType = null, ?int $referenceId = null, ?string $referenceNumber = null): WalletTransaction
    {
        return DB::transaction(function () use ($customerId, $type, $signedAmount, $note, $branchId, $referenceType, $referenceId, $referenceNumber) {
            $wallet = $this->wallet($customerId, lock: true);
            $newBalance = (float) $wallet->balance + $signedAmount;

            if ($newBalance < 0 && !$wallet->allow_negative) {
                throw new RuntimeException('Wallet would go negative — operation not allowed.');
            }

            $wallet->balance = $newBalance;
            if ($signedAmount > 0) {
                $wallet->lifetime_credited = (float) $wallet->lifetime_credited + $signedAmount;
            } else {
                $wallet->lifetime_debited = (float) $wallet->lifetime_debited + abs($signedAmount);
            }
            $wallet->save();

            return WalletTransaction::create([
                'customer_id'      => $customerId,
                'branch_id'        => $branchId,
                'type'             => $type,
                'amount'           => $signedAmount,
                'balance_after'    => $newBalance,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'reference_number' => $referenceNumber,
                'note'             => $note,
                'created_by'       => Auth::id(),
            ]);
        });
    }

    private function assertCreditType(string $type): void
    {
        if (!in_array($type, ['credit', 'refund', 'cashback', 'deposit'], true)) {
            throw new RuntimeException("Invalid credit type: {$type}");
        }
    }
}
