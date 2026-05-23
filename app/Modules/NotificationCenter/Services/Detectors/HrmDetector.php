<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrmDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        $pushed = 0;
        $pushed += $this->pendingPayslips();
        $pushed += $this->absentToday();
        return $pushed;
    }

    private function pendingPayslips(): int
    {
        if (!Schema::hasTable('payslips') || !Schema::hasColumn('payslips', 'approval_status')) return 0;
        $count = DB::table('payslips')->where('approval_status', 'submitted')->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'hrm',
            'code'          => 'hrm.payslip_pending',
            'severity'      => 'warning',
            'urgency'       => 65,
            'title'         => "{$count} payslip(s) awaiting approval",
            'message'       => 'Review and approve payroll for the current period.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'hrm.payslip_pending',
            'cooldown_minutes' => 720,
            'actions'       => [['label' => 'menu.payrollApprovals', 'route' => 'hrm-payroll-approvals', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function absentToday(): int
    {
        if (!Schema::hasTable('attendances') || !Schema::hasTable('employees')) return 0;

        $today = today()->toDateString();
        $totalEmployees = DB::table('employees')->where('status', 'active')->count();
        if ($totalEmployees === 0) return 0;

        $present = DB::table('attendances')->whereDate('attendance_date', $today)->count();
        $absent  = max($totalEmployees - $present, 0);
        if ($absent === 0) return 0;
        if (now()->hour < 11) return 0;       // wait until late morning

        $this->notify->push([
            'category'      => 'hrm',
            'code'          => 'hrm.absent_today',
            'severity'      => $absent > ($totalEmployees * 0.2) ? 'danger' : 'warning',
            'urgency'       => 60,
            'title'         => "{$absent} employee(s) without check-in today",
            'message'       => 'Verify attendance or absence approvals.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'hrm.absent_today:'.$today,
            'cooldown_minutes' => 240,
            'actions'       => [['label' => 'menu.attendance', 'route' => 'hrm-attendance-daily', 'type' => 'primary']],
            'meta'          => ['absent' => $absent, 'total' => $totalEmployees],
        ]);
        return 1;
    }
}
