<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaleAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

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
        try {
            $this->posting->postSale($sale);
        } catch (Throwable $e) {
            Log::error('Sale auto-posting failed', [
                'sale_id' => $sale->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
