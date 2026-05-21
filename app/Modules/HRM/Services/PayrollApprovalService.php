<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Payslip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approval workflow for payslips, layered on top of the existing payment
 * status field. Lifecycle:
 *
 *   draft → submitted → approved → (paid via existing flow)
 *                    ↘ rejected
 *
 * Approved payslips become immutable: the existing payslip controller and
 * service must respect Payslip::isPayable() before allowing payment.
 *
 * The PayslipAccountingObserver was updated to require approval_status
 * = 'approved' before posting the salary journal, so unapproved payslips
 * never reach Accounting even if their payment status flips.
 */
class PayrollApprovalService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function submit(Payslip $payslip, ?string $note = null): Payslip
    {
        if ($payslip->approval_status !== 'draft') {
            throw new RuntimeException("Only draft payslips can be submitted (current: {$payslip->approval_status}).");
        }

        return DB::transaction(function () use ($payslip, $note) {
            $before = ['approval_status' => $payslip->approval_status];
            $payslip->update([
                'approval_status' => 'submitted',
                'submitted_at'    => now(),
                'submitted_by'    => Auth::id(),
            ]);
            $this->audit->log('payslip.submitted', 'payslip', $payslip->id, $before, [
                'approval_status' => 'submitted',
            ], $note);
            return $payslip->fresh();
        });
    }

    public function approve(Payslip $payslip, ?string $note = null): Payslip
    {
        if ($payslip->approval_status !== 'submitted') {
            throw new RuntimeException("Only submitted payslips can be approved (current: {$payslip->approval_status}).");
        }

        return DB::transaction(function () use ($payslip, $note) {
            $before = ['approval_status' => $payslip->approval_status];
            $payslip->update([
                'approval_status' => 'approved',
                'approved_at'     => now(),
                'approved_by'     => Auth::id(),
                // Approved payslips become structurally immutable (status can still flip to paid).
                'is_locked'       => true,
            ]);
            $this->audit->log('payslip.approved', 'payslip', $payslip->id, $before, [
                'approval_status' => 'approved',
                'is_locked'       => true,
            ], $note);
            return $payslip->fresh();
        });
    }

    public function reject(Payslip $payslip, string $reason): Payslip
    {
        if ($payslip->approval_status !== 'submitted') {
            throw new RuntimeException('Only submitted payslips can be rejected.');
        }

        return DB::transaction(function () use ($payslip, $reason) {
            $before = ['approval_status' => $payslip->approval_status];
            $payslip->update([
                'approval_status'   => 'rejected',
                'rejected_at'       => now(),
                'rejected_by'       => Auth::id(),
                'rejection_reason'  => $reason,
            ]);
            $this->audit->log('payslip.rejected', 'payslip', $payslip->id, $before, [
                'approval_status' => 'rejected',
                'rejection_reason'=> $reason,
            ]);
            return $payslip->fresh();
        });
    }

    /**
     * Return a rejected payslip to draft so the HR team can adjust and resubmit.
     */
    public function reopen(Payslip $payslip): Payslip
    {
        if (!in_array($payslip->approval_status, ['rejected', 'submitted'], true)) {
            throw new RuntimeException('Only rejected or submitted payslips can be reopened.');
        }
        if ($payslip->is_locked) {
            throw new RuntimeException('Locked payslips cannot be reopened.');
        }

        return DB::transaction(function () use ($payslip) {
            $before = ['approval_status' => $payslip->approval_status];
            $payslip->update([
                'approval_status' => 'draft',
                'submitted_at'    => null, 'submitted_by'    => null,
                'rejected_at'     => null, 'rejected_by'     => null,
                'rejection_reason'=> null,
            ]);
            $this->audit->log('payslip.reopened', 'payslip', $payslip->id, $before, [
                'approval_status' => 'draft',
            ]);
            return $payslip->fresh();
        });
    }

    public function queueCounts(?int $branchId = null): array
    {
        $base = Payslip::query();
        if ($branchId) $base->where('branch_id', $branchId);
        $clone = fn() => (clone $base);
        return [
            'draft'     => $clone()->where('approval_status', 'draft')->count(),
            'submitted' => $clone()->where('approval_status', 'submitted')->count(),
            'approved'  => $clone()->where('approval_status', 'approved')->count(),
            'rejected'  => $clone()->where('approval_status', 'rejected')->count(),
        ];
    }
}
