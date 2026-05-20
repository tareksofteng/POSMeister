<?php

namespace App\Modules\CRM\Observers;

use App\Modules\CRM\Services\LoyaltyService;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Awards loyalty points when a sale becomes active. The observer never
 * blocks the originating save — posting failures are logged.
 */
class SaleLoyaltyObserver
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function created(Sale $sale): void
    {
        $this->tryPost($sale);
    }

    public function updated(Sale $sale): void
    {
        if ($sale->wasChanged('status')) {
            $this->tryPost($sale);
        }
    }

    private function tryPost(Sale $sale): void
    {
        if ($sale->status !== 'active' || !$sale->customer_id) return;

        try {
            $eligible = (float) $sale->subtotal - (float) $sale->discount_amount;
            if ($eligible <= 0) return;

            $this->loyalty->earnFromSale(
                customerId: (int) $sale->customer_id,
                saleId: (int) $sale->id,
                saleNumber: (string) $sale->sale_number,
                eligibleAmount: $eligible,
                branchId: $sale->branch_id,
            );
        } catch (Throwable $e) {
            Log::error('Loyalty earn-from-sale failed', [
                'sale_id' => $sale->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
