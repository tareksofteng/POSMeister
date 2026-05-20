<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Purchase\Models\Purchase;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaseAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

    public function created(Purchase $purchase): void
    {
        if ($purchase->status === 'received') {
            $this->tryPost($purchase);
        }
    }

    public function updated(Purchase $purchase): void
    {
        if ($purchase->wasChanged('status') && $purchase->status === 'received') {
            $this->tryPost($purchase);
        }
    }

    private function tryPost(Purchase $purchase): void
    {
        try {
            $this->posting->postPurchase($purchase);
        } catch (Throwable $e) {
            Log::error('Purchase auto-posting failed', [
                'purchase_id' => $purchase->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
