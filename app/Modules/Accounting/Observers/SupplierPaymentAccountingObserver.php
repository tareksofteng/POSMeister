<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Purchase\Models\SupplierPayment;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupplierPaymentAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

    public function created(SupplierPayment $payment): void
    {
        try {
            $this->posting->postSupplierPayment($payment);
        } catch (Throwable $e) {
            Log::error('Supplier payment auto-posting failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
