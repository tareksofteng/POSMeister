<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\PayrollPeriod;
use App\Modules\HRM\Models\Payslip;
use App\Modules\HRM\Models\PayslipItem;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    // ---- Periods ----------------------------------------------------------

    public function paginatePeriods(array $filters = []): LengthAwarePaginator
    {
        $q = PayrollPeriod::query()
            ->with('branch:id,name')
            ->withCount(['payslips', 'payslips as paid_count' => fn($p) => $p->where('status', 'paid')])
            ->withSum('payslips as net_total', 'net_salary');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['year'])) {
            $q->whereYear('period_start', $filters['year']);
        }

        return $q->orderByDesc('period_start')->paginate($filters['per_page'] ?? 20);
    }

    public function findPeriod(int $id): PayrollPeriod
    {
        return PayrollPeriod::with('branch:id,name')->findOrFail($id);
    }

    public function createPeriod(array $data): PayrollPeriod
    {
        return PayrollPeriod::create([
            'label'        => $data['label'],
            'period_start' => $data['period_start'],
            'period_end'   => $data['period_end'],
            'branch_id'    => $data['branch_id'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'status'       => 'draft',
        ]);
    }

    public function updatePeriod(PayrollPeriod $period, array $data): PayrollPeriod
    {
        if (!$period->isEditable()) {
            throw new \RuntimeException('Diese Lohnperiode kann nicht mehr bearbeitet werden.');
        }
        $period->update($data);
        return $period->fresh();
    }

    public function deletePeriod(PayrollPeriod $period): void
    {
        if ($period->status !== 'draft') {
            throw new \RuntimeException('Nur Entwurfsperioden können gelöscht werden.');
        }
        $period->delete();
    }

    /**
     * Generate a fresh payslip for every active employee in scope. Existing
     * payslips for the same period are kept untouched so re-running is safe.
     */
    public function generatePayslips(PayrollPeriod $period): array
    {
        $employees = Employee::where('status', 'active');
        if ($period->branch_id) {
            $employees->where('branch_id', $period->branch_id);
        }
        $employees = $employees->get();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($period, $employees, &$created, &$skipped) {
            foreach ($employees as $employee) {
                $exists = Payslip::where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $stats = $this->attendanceSummary($employee->id, $period->period_start, $period->period_end);

                $payslip = Payslip::create([
                    'payslip_number'   => $this->generateNumber(),
                    'payroll_period_id'=> $period->id,
                    'employee_id'      => $employee->id,
                    'branch_id'        => $employee->branch_id,
                    'period_start'     => $period->period_start,
                    'period_end'       => $period->period_end,
                    'days_in_period'   => $stats['days_in_period'],
                    'days_worked'      => $stats['days_worked'],
                    'days_absent'      => $stats['days_absent'],
                    'days_leave'       => $stats['days_leave'],
                    'days_late'        => $stats['days_late'],
                    'days_half'        => $stats['days_half'],
                    'basic_salary'     => $employee->basic_salary,
                    'status'           => 'pending',
                ]);

                $this->recalculate($payslip->fresh('items'));
                $created++;
            }

            if ($period->status === 'draft' && $created > 0) {
                $period->update(['status' => 'generated']);
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function finalizePeriod(PayrollPeriod $period): PayrollPeriod
    {
        if (!in_array($period->status, ['generated', 'finalized'])) {
            throw new \RuntimeException('Nur generierte Perioden können abgeschlossen werden.');
        }
        $period->update(['status' => 'finalized']);
        return $period->fresh();
    }

    // ---- Payslips ---------------------------------------------------------

    public function paginatePayslips(array $filters = []): LengthAwarePaginator
    {
        $q = Payslip::query()
            ->with(['employee:id,first_name,last_name,employee_id,photo', 'period:id,label']);

        if (!empty($filters['payroll_period_id'])) {
            $q->where('payroll_period_id', $filters['payroll_period_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['branch_id'])) {
            $q->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['employee_id'])) {
            $q->where('employee_id', $filters['employee_id']);
        }

        return $q->orderBy('id')->paginate($filters['per_page'] ?? 100);
    }

    public function findPayslip(int $id): Payslip
    {
        return Payslip::with([
            'employee.department:id,name',
            'employee.designation:id,title',
            'employee.shift:id,name,start_time,end_time',
            'period:id,label,period_start,period_end',
            'branch:id,name,phone,address',
            'items',
            'creator:id,name',
        ])->findOrFail($id);
    }

    public function updatePayslip(Payslip $payslip, array $data): Payslip
    {
        $payslip->update($data);
        $this->recalculate($payslip->fresh('items'));
        return $this->findPayslip($payslip->id);
    }

    public function addItem(Payslip $payslip, array $data): Payslip
    {
        PayslipItem::create([
            'payslip_id' => $payslip->id,
            'type'       => $data['type'],
            'name'       => $data['name'],
            'amount'     => $data['amount'],
            'notes'      => $data['notes'] ?? null,
        ]);
        $this->recalculate($payslip->fresh('items'));
        return $this->findPayslip($payslip->id);
    }

    public function removeItem(Payslip $payslip, int $itemId): Payslip
    {
        PayslipItem::where('payslip_id', $payslip->id)->where('id', $itemId)->delete();
        $this->recalculate($payslip->fresh('items'));
        return $this->findPayslip($payslip->id);
    }

    public function pay(Payslip $payslip, array $data): Payslip
    {
        $paid = (float) ($data['paid_amount'] ?? $payslip->net_salary);
        $status = abs($paid - (float) $payslip->net_salary) < 0.01
            ? 'paid'
            : ($paid > 0 ? 'partially_paid' : 'pending');

        $payslip->update([
            'paid_amount'       => $paid,
            'payment_date'      => $data['payment_date'] ?? now()->toDateString(),
            'payment_method'    => $data['payment_method'] ?? 'bank_transfer',
            'payment_reference' => $data['payment_reference'] ?? null,
            'status'            => $status,
        ]);

        return $this->findPayslip($payslip->id);
    }

    public function destroyPayslip(Payslip $payslip): void
    {
        if (in_array($payslip->status, ['paid', 'partially_paid'])) {
            throw new \RuntimeException('Bezahlte Abrechnungen können nicht gelöscht werden.');
        }
        $payslip->delete();
    }

    // ---- Calculation engine ----------------------------------------------

    /**
     * Sum the line items by type, then write the derived totals + gross + net
     * back onto the payslip header.
     */
    public function recalculate(Payslip $payslip): Payslip
    {
        $allowances = (float) $payslip->items->where('type', 'allowance')->sum('amount');
        $bonuses    = (float) $payslip->items->where('type', 'bonus')->sum('amount');
        $overtime   = (float) $payslip->items->where('type', 'overtime')->sum('amount');
        $deductions = (float) $payslip->items->where('type', 'deduction')->sum('amount');
        $tax        = (float) $payslip->items->where('type', 'tax')->sum('amount');

        $basic = (float) $payslip->basic_salary;
        $gross = $basic + $allowances + $bonuses + $overtime;
        $net   = $gross - $deductions - $tax;

        $payslip->update([
            'total_allowances' => round($allowances, 2),
            'total_bonuses'    => round($bonuses, 2),
            'total_overtime'   => round($overtime, 2),
            'total_deductions' => round($deductions, 2),
            'tax_amount'       => round($tax, 2),
            'gross_salary'     => round($gross, 2),
            'net_salary'       => round(max(0, $net), 2),
        ]);

        return $payslip->fresh();
    }

    private function attendanceSummary(int $employeeId, $start, $end): array
    {
        $records = Attendance::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$start, $end])
            ->get(['status']);

        $days = CarbonPeriod::create($start, $end)->count();
        $counts = $records->groupBy('status')->map->count();

        return [
            'days_in_period' => $days,
            'days_worked'    => (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0),
            'days_absent'    => (int) ($counts['absent'] ?? 0),
            'days_leave'     => (int) ($counts['leave']  ?? 0),
            'days_late'      => (int) ($counts['late']   ?? 0),
            'days_half'      => (int) ($counts['half_day'] ?? 0),
        ];
    }

    private function generateNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = "PS-{$year}-";
        $last   = Payslip::withTrashed()
            ->where('payslip_number', 'like', $prefix . '%')
            ->max('payslip_number');
        $next = $last ? ((int) substr($last, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
