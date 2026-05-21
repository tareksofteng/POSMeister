<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Payslip;
use App\Modules\HRM\Models\SalaryAdvance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Salary advance ledger. Advances are granted manually, then auto-deducted
 * from the next draft payslip the employee receives. Settled in full as
 * soon as the deducted_amount reaches the granted amount.
 */
class SalaryAdvanceService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function grant(int $employeeId, float $amount, string $reason = '', ?int $branchId = null, ?string $grantedOn = null): SalaryAdvance
    {
        if ($amount <= 0) {
            throw new RuntimeException('Advance amount must be positive.');
        }

        return DB::transaction(function () use ($employeeId, $amount, $reason, $branchId, $grantedOn) {
            $employee = Employee::findOrFail($employeeId);
            $advance = SalaryAdvance::create([
                'employee_id' => $employee->id,
                'branch_id'   => $branchId ?? $employee->branch_id,
                'granted_on'  => $grantedOn ?? now()->toDateString(),
                'amount'      => $amount,
                'reason'      => $reason ?: 'Salary advance',
                'status'      => 'outstanding',
            ]);
            $this->audit->log('advance.granted', 'salary_advance', $advance->id, null, [
                'employee_id' => $employee->id,
                'amount'      => $amount,
            ], $reason);
            return $advance->fresh();
        });
    }

    /**
     * Idempotently deducts outstanding advances from a draft payslip.
     * Returns the total deducted. The amount is also written to the
     * payslip's `advance_deducted` and `total_deductions` columns so
     * the net_salary stays consistent.
     */
    public function deductFromPayslip(Payslip $payslip): float
    {
        if ($payslip->approval_status !== 'draft') {
            // Locked / submitted / approved / paid payslips are off-limits.
            return 0.0;
        }

        return DB::transaction(function () use ($payslip) {
            $advances = SalaryAdvance::outstanding()
                ->where('employee_id', $payslip->employee_id)
                ->lockForUpdate()
                ->orderBy('granted_on')
                ->get();

            $netCap = max(0, (float) $payslip->gross_salary - (float) $payslip->total_deductions);
            $available = $netCap;
            $totalDeducted = 0.0;

            foreach ($advances as $advance) {
                if ($available <= 0) break;

                $remaining = $advance->outstandingAmount();
                if ($remaining <= 0) continue;

                $take = min($remaining, $available);
                $advance->deducted_amount = (float) $advance->deducted_amount + $take;

                if (abs((float) $advance->deducted_amount - (float) $advance->amount) < 0.01) {
                    $advance->status = 'settled';
                    $advance->settled_in_payslip_id = $payslip->id;
                } else {
                    $advance->status = 'partially_deducted';
                }
                $advance->save();

                $totalDeducted += $take;
                $available     -= $take;
            }

            if ($totalDeducted > 0) {
                $payslip->advance_deducted  = (float) $payslip->advance_deducted + $totalDeducted;
                $payslip->total_deductions  = (float) $payslip->total_deductions + $totalDeducted;
                $payslip->net_salary        = max(0, (float) $payslip->gross_salary - (float) $payslip->total_deductions);
                $payslip->save();

                $this->audit->log('advance.deducted', 'payslip', $payslip->id, null, [
                    'deducted' => round($totalDeducted, 2),
                ]);
            }

            return round($totalDeducted, 2);
        });
    }

    public function cancel(SalaryAdvance $advance, string $reason): SalaryAdvance
    {
        if ($advance->status === 'settled') {
            throw new RuntimeException('Settled advances cannot be cancelled.');
        }
        return DB::transaction(function () use ($advance, $reason) {
            $before = ['status' => $advance->status, 'amount' => (float) $advance->amount];
            $advance->update(['status' => 'cancelled', 'notes' => $reason]);
            $this->audit->log('advance.cancelled', 'salary_advance', $advance->id, $before, ['status' => 'cancelled'], $reason);
            return $advance->fresh();
        });
    }

    public function outstandingTotal(int $employeeId): float
    {
        return (float) SalaryAdvance::outstanding()
            ->where('employee_id', $employeeId)
            ->selectRaw('COALESCE(SUM(amount - deducted_amount), 0) as total')
            ->value('total');
    }
}
