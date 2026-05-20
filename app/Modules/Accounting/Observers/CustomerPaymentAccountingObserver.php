<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Sales\Models\CustomerPayment;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerPaymentAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

    public function created(CustomerPayment $payment): void
    {
        try {
            $this->posting->postCustomerPayment($payment);
        } catch (Throwable $e) {
            Log::error('Customer payment auto-posting failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
