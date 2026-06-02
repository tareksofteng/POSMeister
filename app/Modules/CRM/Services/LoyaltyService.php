<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\CustomerLoyaltyProfile;
use App\Modules\CRM\Models\LoyaltySettings;
use App\Modules\CRM\Models\LoyaltyTransaction;
use App\Modules\Sales\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loyalty engine. All point movements funnel through `post()` so the
 * profile counters, ledger entry and tier check happen atomically.
 *
 * Transactions are immutable once written. Corrections are made by
 * posting a reversing entry, not by editing.
 */
class LoyaltyService
{
    public const TIERS = ['silver', 'gold', 'platinum', 'vip'];

    public function settings(): LoyaltySettings
    {
        return LoyaltySettings::current();
    }

    /**
     * Award points for a sale. Called by the observer. Idempotent:
     * a second call for the same sale is a no-op.
     */
    public function earnFromSale(int $customerId, int $saleId, string $saleNumber, float $eligibleAmount, ?int $branchId): ?LoyaltyTransaction
    {
        $settings = $this->settings();
        if (!$settings->enabled || $eligibleAmount <= 0) return null;

        if ($this->existing('sale', $saleId)) return null;

        $points = round($eligibleAmount * (float) $settings->earn_per_currency, 2);
        if ($points <= 0) return null;

        return $this->post(
            customerId: $customerId,
            type: 'earn',
            points: $points,
            note: 'Verkauf ' . $saleNumber,
            referenceType: 'sale',
            referenceId: $saleId,
            referenceNumber: $saleNumber,
            branchId: $branchId,
            spendDelta: $eligibleAmount,
            visitDelta: 1,
        );
    }

    /**
     * Reverse points awarded on a sale return.
     */
    public function reverseForReturn(int $customerId, int $returnId, string $returnNumber, float $refundedAmount, ?int $branchId): ?LoyaltyTransaction
    {
        $settings = $this->settings();
        if (!$settings->enabled || $refundedAmount <= 0) return null;

        if ($this->existing('sale_return', $returnId)) return null;

        $points = round($refundedAmount * (float) $settings->earn_per_currency, 2);
        if ($points <= 0) return null;

        return $this->post(
            customerId: $customerId,
            type: 'reverse',
            points: -$points,
            note: 'Rücknahme ' . $returnNumber,
            referenceType: 'sale_return',
            referenceId: $returnId,
            referenceNumber: $returnNumber,
            branchId: $branchId,
            spendDelta: -$refundedAmount,
        );
    }

    /**
     * Manual redemption — customer trades points for store credit.
     * Returns the value (in currency) of the redeemed points.
     */
    public function redeem(int $customerId, int $points, string $note = '', ?int $branchId = null, ?int $saleId = null, ?string $saleNumber = null): array
    {
        $settings = $this->settings();
        if (!$settings->enabled) {
            throw new RuntimeException('Loyalty programme is disabled.');
        }
        if ($points < $settings->min_redeem_points) {
            throw new RuntimeException(__('errors.loyalty.min_redeem', ['min' => $settings->min_redeem_points]));
        }

        return DB::transaction(function () use ($customerId, $points, $note, $branchId, $saleId, $saleNumber, $settings) {
            $profile = $this->profile($customerId, lock: true);

            if ($profile->current_points < $points) {
                throw new RuntimeException(__('errors.loyalty.not_enough'));
            }

            $txn = $this->post(
                customerId: $customerId,
                type: 'redeem',
                points: -$points,
                note: $note ?: 'Punkte eingelöst',
                referenceType: $saleId ? 'sale' : 'manual_redeem',
                referenceId: $saleId,
                referenceNumber: $saleNumber,
                branchId: $branchId,
                profile: $profile,
            );

            $value = round($points / max(1, $settings->redeem_points_per_currency), 2);
            return ['transaction' => $txn, 'redeemed_value' => $value];
        });
    }

    /**
     * Admin / manager adjusts a customer's balance manually.
     */
    public function adjust(int $customerId, float $points, string $note, ?int $branchId = null): LoyaltyTransaction
    {
        if (abs($points) < 0.01) {
            throw new RuntimeException('Adjustment cannot be zero.');
        }
        return $this->post(
            customerId: $customerId,
            type: 'adjust',
            points: $points,
            note: $note ?: 'Manual adjustment',
            referenceType: 'manual',
            branchId: $branchId,
        );
    }

    /**
     * Quick balance + tier read for the profile / POS terminal.
     */
    public function summary(int $customerId): array
    {
        $profile = $this->profile($customerId);
        $settings = $this->settings();

        $value = $profile->current_points > 0
            ? round((float) $profile->current_points / max(1, $settings->redeem_points_per_currency), 2)
            : 0.0;

        $next = $this->nextTierInfo($profile->tier, (float) $profile->lifetime_spent, $settings);

        return [
            'customer_id'             => $profile->customer_id,
            'tier'                    => $profile->tier,
            'current_points'          => (float) $profile->current_points,
            'redeemable_value'        => $value,
            'lifetime_points_earned'  => (float) $profile->lifetime_points_earned,
            'lifetime_points_redeemed'=> (float) $profile->lifetime_points_redeemed,
            'lifetime_spent'          => (float) $profile->lifetime_spent,
            'lifetime_visits'         => (int) $profile->lifetime_visits,
            'tier_discount_percent'   => $this->tierDiscount($profile->tier, $settings),
            'next_tier'               => $next,
        ];
    }

    /**
     * Returns the profile for a customer, creating it lazily if missing.
     * Pass lock=true inside a DB transaction to acquire a row lock for
     * concurrent-safe balance updates.
     */
    public function profile(int $customerId, bool $lock = false): CustomerLoyaltyProfile
    {
        $q = CustomerLoyaltyProfile::where('customer_id', $customerId);
        if ($lock) $q->lockForUpdate();

        $profile = $q->first();
        if (!$profile) {
            $profile = CustomerLoyaltyProfile::create([
                'customer_id'  => $customerId,
                'tier'         => 'silver',
                'tier_changed_at' => now(),
            ]);
            if ($lock) {
                $profile = CustomerLoyaltyProfile::where('id', $profile->id)->lockForUpdate()->first();
            }
        }
        return $profile;
    }

    /**
     * Central writer. Updates profile counters + writes a ledger row
     * under a single DB transaction. Re-evaluates tier afterwards.
     */
    private function post(
        int $customerId,
        string $type,
        float $points,
        string $note,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?int $branchId = null,
        float $spendDelta = 0,
        int $visitDelta = 0,
        ?CustomerLoyaltyProfile $profile = null,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customerId, $type, $points, $note, $referenceType, $referenceId, $referenceNumber, $branchId, $spendDelta, $visitDelta, $profile) {

            $profile ??= $this->profile($customerId, lock: true);

            $profile->current_points = (float) $profile->current_points + $points;

            if ($points > 0 && $type !== 'reverse') {
                $profile->lifetime_points_earned = (float) $profile->lifetime_points_earned + $points;
            } elseif ($points < 0 && $type === 'redeem') {
                $profile->lifetime_points_redeemed = (float) $profile->lifetime_points_redeemed + abs($points);
            }

            if (abs($spendDelta) > 0.001) {
                $profile->lifetime_spent = max(0, (float) $profile->lifetime_spent + $spendDelta);
            }
            if ($visitDelta) {
                $profile->lifetime_visits = max(0, (int) $profile->lifetime_visits + $visitDelta);
            }
            $profile->last_activity_at = now();

            $newTier = $this->evaluateTier((float) $profile->lifetime_spent, $profile->tier);
            if ($newTier !== $profile->tier) {
                $profile->tier = $newTier;
                $profile->tier_changed_at = now();
            }

            $profile->save();

            $txn = LoyaltyTransaction::create([
                'customer_id'      => $customerId,
                'branch_id'        => $branchId,
                'type'             => $type,
                'points'           => $points,
                'balance_after'    => $profile->current_points,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'reference_number' => $referenceNumber,
                'note'             => $note,
                'created_by'       => Auth::id(),
            ]);

            return $txn;
        });
    }

    private function evaluateTier(float $lifetimeSpent, string $currentTier): string
    {
        $settings = $this->settings();
        $newTier = 'silver';
        if ($lifetimeSpent >= (float) $settings->tier_vip_min)       $newTier = 'vip';
        elseif ($lifetimeSpent >= (float) $settings->tier_platinum_min) $newTier = 'platinum';
        elseif ($lifetimeSpent >= (float) $settings->tier_gold_min)     $newTier = 'gold';

        if ($settings->auto_downgrade) return $newTier;

        // No-auto-downgrade: only promote, never demote.
        $currentRank = array_search($currentTier, self::TIERS);
        $newRank     = array_search($newTier, self::TIERS);
        return $newRank > $currentRank ? $newTier : $currentTier;
    }

    private function nextTierInfo(string $tier, float $lifetimeSpent, LoyaltySettings $s): ?array
    {
        $next = match ($tier) {
            'silver'   => ['name' => 'gold',     'threshold' => (float) $s->tier_gold_min],
            'gold'     => ['name' => 'platinum', 'threshold' => (float) $s->tier_platinum_min],
            'platinum' => ['name' => 'vip',      'threshold' => (float) $s->tier_vip_min],
            default    => null,
        };
        if (!$next) return null;

        $remaining = max(0, $next['threshold'] - $lifetimeSpent);
        $progress  = $next['threshold'] > 0
            ? min(100, round(($lifetimeSpent / $next['threshold']) * 100, 1))
            : 100;

        return [
            'name'              => $next['name'],
            'threshold'         => $next['threshold'],
            'remaining_spend'   => round($remaining, 2),
            'progress_percent'  => $progress,
        ];
    }

    private function tierDiscount(string $tier, LoyaltySettings $s): float
    {
        return (float) match ($tier) {
            'gold'     => $s->tier_gold_discount,
            'platinum' => $s->tier_platinum_discount,
            'vip'      => $s->tier_vip_discount,
            default    => $s->tier_silver_discount,
        };
    }

    private function existing(string $referenceType, int $referenceId): ?LoyaltyTransaction
    {
        return LoyaltyTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();
    }
}
