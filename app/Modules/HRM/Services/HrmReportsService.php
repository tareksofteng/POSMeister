<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\PayrollPeriod;
use App\Modules\HRM\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HrmReportsService
{
    public function dashboard(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd   = $today->copy()->endOfMonth();

        $byStatus = $this->scopedEmployees()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $byDepartment = $this->scopedEmployees()
            ->where('status', 'active')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('departments.id, departments.name, count(employees.id) as count')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->get();

        $byBranch = $this->scopedEmployees()
            ->where('status', 'active')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->selectRaw('branches.id, branches.name, count(employees.id) as count')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('count')
            ->get();

        $byEmploymentType = $this->scopedEmployees()
            ->where('status', 'active')
            ->selectRaw('employment_type, count(*) as count')
            ->groupBy('employment_type')
            ->pluck('count', 'employment_type');

        $byGender = $this->scopedEmployees()
            ->where('status', 'active')
            ->selectRaw('gender, count(*) as count')
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Today's attendance snapshot
        $todayCounts = Attendance::whereDate('attendance_date', $today)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // Payroll for the running month (sum of net across periods overlapping this month)
        $monthlyNet = Payslip::join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
            ->whereBetween('payroll_periods.period_start', [$monthStart, $monthEnd])
            ->sum('payslips.net_salary');

        $openPayslips = Payslip::whereIn('status', ['pending', 'partially_paid'])->count();

        $recentHires = $this->scopedEmployees()
            ->where('joining_date', '>=', $today->copy()->subDays(30))
            ->orderByDesc('joining_date')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'employee_id', 'joining_date', 'photo', 'designation_id'])
            ->load('designation:id,title')
            ->map(fn(Employee $e) => [
                'id'           => $e->id,
                'employee_id'  => $e->employee_id,
                'name'         => $e->full_name,
                'designation'  => $e->designation?->title,
                'joining_date' => $e->joining_date?->format('Y-m-d'),
            ]);

        return [
            'kpis' => [
                'total_employees'      => (int) $byStatus->sum(),
                'active'               => (int) ($byStatus['active'] ?? 0),
                'inactive'             => (int) ($byStatus['inactive'] ?? 0),
                'terminated'           => (int) ($byStatus['terminated'] ?? 0),
                'resigned'             => (int) ($byStatus['resigned'] ?? 0),
                'present_today'        => (int) (($todayCounts['present'] ?? 0) + ($todayCounts['late'] ?? 0)),
                'absent_today'         => (int) ($todayCounts['absent'] ?? 0),
                'on_leave_today'       => (int) ($todayCounts['leave'] ?? 0),
                'monthly_payroll_net'  => round((float) $monthlyNet, 2),
                'open_payslips'        => $openPayslips,
            ],
            'by_department'      => $byDepartment,
            'by_branch'          => $byBranch,
            'by_employment_type' => $byEmploymentType,
            'by_gender'          => $byGender,
            'recent_hires'       => $recentHires,
        ];
    }

    public function attendance(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::today()->startOfMonth()->toDateString();
        $to   = $filters['to']   ?? Carbon::today()->toDateString();

        $query = Attendance::query()->whereBetween('attendance_date', [$from, $to]);
        $this->applyBranchScope($query, $filters['branch_id'] ?? null);

        $totals = (clone $query)->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $byDepartment = (clone $query)
            ->join('employees', 'employees.id', '=', 'attendance.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('departments.name as department, attendance.status, count(*) as c')
            ->groupBy('departments.name', 'attendance.status')
            ->get();

        $deptMatrix = [];
        foreach ($byDepartment as $row) {
            $name = $row->department;
            if (!isset($deptMatrix[$name])) {
                $deptMatrix[$name] = ['department' => $name, 'present' => 0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'half_day' => 0];
            }
            $deptMatrix[$name][$row->status] = (int) $row->c;
        }

        $dailyTrend = (clone $query)
            ->selectRaw('attendance_date, status, count(*) as c')
            ->groupBy('attendance_date', 'status')
            ->orderBy('attendance_date')
            ->get();

        $trendByDate = [];
        foreach ($dailyTrend as $row) {
            $d = (string) $row->attendance_date;
            if (!isset($trendByDate[$d])) {
                $trendByDate[$d] = ['date' => $d, 'present' => 0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'half_day' => 0];
            }
            $trendByDate[$d][$row->status] = (int) $row->c;
        }

        $topPunctual = (clone $query)
            ->where('status', 'present')
            ->join('employees', 'employees.id', '=', 'attendance.employee_id')
            ->selectRaw('employees.id, employees.first_name, employees.last_name, employees.employee_id, count(*) as days')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.employee_id')
            ->orderByDesc('days')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'employee_id' => $r->employee_id,
                'name'        => trim($r->first_name . ' ' . $r->last_name),
                'days'        => (int) $r->days,
            ]);

        $topAbsent = (clone $query)
            ->where('status', 'absent')
            ->join('employees', 'employees.id', '=', 'attendance.employee_id')
            ->selectRaw('employees.id, employees.first_name, employees.last_name, employees.employee_id, count(*) as days')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.employee_id')
            ->orderByDesc('days')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'employee_id' => $r->employee_id,
                'name'        => trim($r->first_name . ' ' . $r->last_name),
                'days'        => (int) $r->days,
            ]);

        return [
            'period'        => ['from' => $from, 'to' => $to],
            'totals'        => [
                'present'  => (int) ($totals['present']  ?? 0),
                'absent'   => (int) ($totals['absent']   ?? 0),
                'leave'    => (int) ($totals['leave']    ?? 0),
                'late'     => (int) ($totals['late']     ?? 0),
                'half_day' => (int) ($totals['half_day'] ?? 0),
            ],
            'by_department' => array_values($deptMatrix),
            'daily_trend'   => array_values($trendByDate),
            'top_punctual'  => $topPunctual,
            'top_absent'    => $topAbsent,
        ];
    }

    public function payroll(?int $periodId = null): array
    {
        $period = $periodId
            ? PayrollPeriod::find($periodId)
            : PayrollPeriod::orderByDesc('period_start')->first();

        if (!$period) {
            return [
                'period'        => null,
                'totals'        => null,
                'by_department' => [],
                'by_branch'     => [],
                'top_earners'   => [],
            ];
        }

        $query = Payslip::where('payroll_period_id', $period->id);

        $totals = (clone $query)
            ->selectRaw('
                count(*) as count,
                sum(basic_salary) as basic,
                sum(total_allowances) as allowances,
                sum(total_bonuses) as bonuses,
                sum(total_overtime) as overtime,
                sum(total_deductions) as deductions,
                sum(tax_amount) as tax,
                sum(gross_salary) as gross,
                sum(net_salary) as net,
                sum(paid_amount) as paid
            ')
            ->first();

        $byDepartment = (clone $query)
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('
                coalesce(departments.name, \'—\') as department,
                count(*) as count,
                sum(payslips.gross_salary) as gross,
                sum(payslips.net_salary) as net
            ')
            ->groupBy('departments.name')
            ->orderByDesc('net')
            ->get();

        $byBranch = (clone $query)
            ->join('branches', 'branches.id', '=', 'payslips.branch_id')
            ->selectRaw('
                branches.name as branch,
                count(*) as count,
                sum(payslips.gross_salary) as gross,
                sum(payslips.net_salary) as net
            ')
            ->groupBy('branches.name')
            ->orderByDesc('net')
            ->get();

        $topEarners = (clone $query)
            ->with('employee:id,first_name,last_name,employee_id,photo')
            ->orderByDesc('net_salary')
            ->limit(5)
            ->get(['id', 'employee_id', 'net_salary', 'gross_salary', 'payslip_number'])
            ->map(fn($p) => [
                'id'             => $p->id,
                'payslip_number' => $p->payslip_number,
                'employee_id'    => $p->employee?->employee_id,
                'name'           => $p->employee ? trim($p->employee->first_name . ' ' . $p->employee->last_name) : null,
                'gross'          => (float) $p->gross_salary,
                'net'            => (float) $p->net_salary,
            ]);

        return [
            'period' => [
                'id'           => $period->id,
                'label'        => $period->label,
                'period_start' => $period->period_start?->format('Y-m-d'),
                'period_end'   => $period->period_end?->format('Y-m-d'),
                'status'       => $period->status,
            ],
            'totals' => [
                'count'      => (int) $totals->count,
                'basic'      => round((float) $totals->basic, 2),
                'allowances' => round((float) $totals->allowances, 2),
                'bonuses'    => round((float) $totals->bonuses, 2),
                'overtime'   => round((float) $totals->overtime, 2),
                'deductions' => round((float) $totals->deductions, 2),
                'tax'        => round((float) $totals->tax, 2),
                'gross'      => round((float) $totals->gross, 2),
                'net'        => round((float) $totals->net, 2),
                'paid'       => round((float) $totals->paid, 2),
            ],
            'by_department' => $byDepartment,
            'by_branch'     => $byBranch,
            'top_earners'   => $topEarners,
        ];
    }

    private function scopedEmployees()
    {
        $q = Employee::query()->from('employees');
        if (Auth::user()?->role !== 'admin' && Auth::user()?->branch_id) {
            $q->where('branch_id', Auth::user()->branch_id);
        }
        return $q;
    }

    private function applyBranchScope($query, ?int $branchId): void
    {
        $user = Auth::user();
        if ($user?->role === 'admin' && $branchId) {
            $query->where('branch_id', $branchId);
            return;
        }
        if ($user?->role !== 'admin' && $user?->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }
}
