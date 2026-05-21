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
        if ($this->isPostable($payslip)) {
            $this->tryPost($payslip);
        }
    }

    public function updated(Payslip $payslip): void
    {
        // Post on payment flip OR on the approval flip that follows a paid status.
        $statusChanged   = $payslip->wasChanged('status') && $payslip->status === 'paid';
        $approvalChanged = $payslip->wasChanged('approval_status') && $payslip->approval_status === 'approved';
        if (($statusChanged || $approvalChanged) && $this->isPostable($payslip)) {
            $this->tryPost($payslip);
        }
    }

    /**
     * Payroll only hits Accounting after HR has explicitly approved it.
     * This blocks accidental drafts from ever touching the ledger.
     */
    private function isPostable(Payslip $payslip): bool
    {
        return $payslip->status === 'paid'
            && $payslip->approval_status === 'approved';
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
