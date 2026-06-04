<?php

namespace App\Modules\Serials\Services;

use App\Modules\Serials\Models\ProductSerial;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/*
 |--------------------------------------------------------------------------
 | WarrantyService
 |--------------------------------------------------------------------------
 |
 | Pure date math + lookup helpers. Calling code (SerialTrackingService,
 | NotificationCenter rules) hands in raw inputs and gets back warranty
 | facts. No state, no database writes here — that's the rest of the
 | service layer's job.
 */
class WarrantyService
{
    /**
     * Derive the warranty expiry date from a purchase date and a duration
     * in months. Returns null when either input is missing so the caller
     * can treat "no warranty" as a first-class concept.
     */
    public function calculateExpiryDate(?string $purchaseDate, ?int $warrantyMonths): ?string
    {
        if (!$purchaseDate || !$warrantyMonths || $warrantyMonths <= 0) {
            return null;
        }
        return Carbon::parse($purchaseDate)
            ->addMonths($warrantyMonths)
            ->toDateString();
    }

    /**
     * Days remaining on a warranty. Negative when expired. Null when no
     * warranty was registered. The UI uses the sign to pick a colour:
     *
     *    >  0  → emerald
     *    0..30 → amber  (use $this->isExpiringSoon())
     *    < 0  → rose
     */
    public function remainingDays(?string $expiryDate): ?int
    {
        if (!$expiryDate) return null;
        return (int) now()->startOfDay()->diffInDays(Carbon::parse($expiryDate), false);
    }

    public function isUnderWarranty(?string $expiryDate): bool
    {
        return ($this->remainingDays($expiryDate) ?? -1) >= 0;
    }

    public function isExpiringSoon(?string $expiryDate, int $thresholdDays = 30): bool
    {
        $days = $this->remainingDays($expiryDate);
        return $days !== null && $days >= 0 && $days <= $thresholdDays;
    }

    /**
     * Bulk query for the notification center: pulls every serial whose
     * warranty expires within the threshold and which hasn't already
     * been flagged. Caller is responsible for de-duping against the
     * notifications table.
     */
    public function expiringSoon(int $thresholdDays = 30, ?int $branchId = null): Collection
    {
        return ProductSerial::query()
            ->with(['product:id,name,sku', 'branch:id,name'])
            ->warrantyExpiringWithinDays($thresholdDays)
            ->forBranch($branchId)
            ->whereIn('status', [ProductSerial::STATUS_SOLD, ProductSerial::STATUS_IN_STOCK])
            ->orderBy('warranty_expiry_date')
            ->get();
    }
}
