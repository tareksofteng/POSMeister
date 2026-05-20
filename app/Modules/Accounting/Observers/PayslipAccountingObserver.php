<?php

namespace App\Modules\Accounting\Observers;

use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\HRM\Models\Payslip;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayslipAccountingObserver
{
    public function __construct(private readonly JournalPostingService $posting) {}

    public function created(Payslip $payslip): void
    {
        if ($payslip->status === 'paid') {
            $this->tryPost($payslip);
        }
    }

    public function updated(Payslip $payslip): void
    {
        if ($payslip->wasChanged('status') && $payslip->status === 'paid') {
            $this->tryPost($payslip);
        }
    }

    private function tryPost(Payslip $payslip): void
    {
        try {
            $this->posting->postPayslip($payslip);
        } catch (Throwable $e) {
            Log::error('Payslip auto-posting failed', [
                'payslip_id' => $payslip->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
