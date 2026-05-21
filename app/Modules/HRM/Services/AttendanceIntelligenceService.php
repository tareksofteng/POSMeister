<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-side analytics + correction handler for attendance. Pure read for
 * the dashboard endpoints; the correction recorder is the only write path.
 */
class AttendanceIntelligenceService
{
    public function __construct(private readonly HrAuditService $audit) {}

    /**
     * Per-employee attendance score for a date range. Heuristic:
     *   100 - (absent% × 1.5) - (late% × 1.0) + half-day handling.
     */
    public function scores(?int $branchId, string $from, string $to, int $limit = 100): array
    {
        $scope = $this->resolveBranchScope($branchId);

        return DB::table('employees as e')
            ->leftJoin('attendance as a', function ($j) use ($from, $to) {
                $j->on('a.employee_id', '=', 'e.id')
                  ->whereBetween('a.attendance_date', [$from, $to]);
            })
            ->where('e.status', 'active')
            ->whereNull('e.deleted_at')
            ->when($scope, fn($q) => $q->where('e.branch_id', $scope))
            ->selectRaw('
                e.id, e.first_name, e.last_name, e.branch_id,
                COUNT(a.id) as days,
                SUM(CASE WHEN a.status = "present"  THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = "absent"   THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = "leave"    THEN 1 ELSE 0 END) as on_leave,
                SUM(CASE WHEN a.status = "half_day" THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN a.is_late = 1 THEN 1 ELSE 0 END) as late_days,
                COALESCE(SUM(a.worked_minutes), 0) as worked_minutes
            ')
            ->groupBy('e.id', 'e.first_name', 'e.last_name', 'e.branch_id')
            ->orderByDesc('present')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $days = (int) $r->days;
                $absentPct = $days > 0 ? ($r->absent / $days) * 100 : 0;
                $latePct   = $days > 0 ? ($r->late_days / $days) * 100 : 0;
                $halfPct   = $days > 0 ? ($r->half_days / $days) * 100 : 0;

                $score = 100 - ($absentPct * 1.5) - $latePct - ($halfPct * 0.5);
                $score = max(0, min(100, $score));

                return [
                    'employee_id'    => (int) $r->id,
                    'name'           => trim($r->first_name . ' ' . $r->last_name),
                    'branch_id'      => (int) $r->branch_id,
                    'days'           => $days,
                    'present'        => (int) $r->present,
                    'absent'         => (int) $r->absent,
                    'on_leave'       => (int) $r->on_leave,
                    'half_days'      => (int) $r->half_days,
                    'late_days'      => (int) $r->late_days,
                    'worked_hours'   => round(((int) $r->worked_minutes) / 60, 1),
                    'late_pct'       => round($latePct, 1),
                    'absent_pct'     => round($absentPct, 1),
                    'score'          => (int) round($score),
                ];
            })->all();
    }

    /**
     * Weekly heatmap: counts of late check-ins by weekday × hour.
     * Returns a 7×24 matrix (rows = Mon..Sun, cols = 0..23).
     */
    public function lateHeatmap(?int $branchId, string $from, string $to): array
    {
        $scope = $this->resolveBranchScope($branchId);

        $rows = DB::table('attendance')
            ->where('is_late', true)
            ->whereBetween('attendance_date', [$from, $to])
            ->whereNotNull('check_in')
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->selectRaw('
                DAYOFWEEK(attendance_date) as dow,
                HOUR(check_in)             as hour,
                COUNT(*)                   as cnt
            ')
            ->groupBy('dow', 'hour')
            ->get();

        // MySQL DAYOFWEEK: 1=Sun..7=Sat. We re-map to Mon=0..Sun=6.
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($rows as $r) {
            $row = ((int) $r->dow + 5) % 7;
            $matrix[$row][(int) $r->hour] = (int) $r->cnt;
        }
        return $matrix;
    }

    public function overtimeTrend(?int $branchId, string $from, string $to): array
    {
        $scope = $this->resolveBranchScope($branchId);

        // Overtime proxy: worked_minutes beyond 8h/day (480 minutes).
        return DB::table('attendance')
            ->whereBetween('attendance_date', [$from, $to])
            ->whereNotNull('worked_minutes')
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->selectRaw('
                attendance_date,
                COALESCE(SUM(GREATEST(worked_minutes - 480, 0)), 0) as overtime_minutes
            ')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->map(fn($r) => [
                'date'             => $r->attendance_date,
                'overtime_hours'   => round(((int) $r->overtime_minutes) / 60, 1),
            ])->all();
    }

    public function breakAnalytics(?int $branchId, string $from, string $to): array
    {
        $scope = $this->resolveBranchScope($branchId);

        $rows = DB::table('attendance')
            ->whereBetween('attendance_date', [$from, $to])
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->whereNotNull('worked_minutes')
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MINUTE, check_in, check_out) - worked_minutes) as avg_break_minutes,
                AVG(worked_minutes) as avg_worked_minutes
            ')
            ->first();

        return [
            'avg_break_minutes'  => round((float) ($rows->avg_break_minutes ?? 0), 1),
            'avg_worked_minutes' => round((float) ($rows->avg_worked_minutes ?? 0), 1),
        ];
    }

    /**
     * Recorded correction with before / after snapshots.
     */
    public function correct(Attendance $attendance, array $newValues, ?string $reason = null): Attendance
    {
        $before = $attendance->only([
            'status', 'check_in', 'check_out', 'worked_minutes', 'is_late', 'remarks',
        ]);

        return DB::transaction(function () use ($attendance, $newValues, $reason, $before) {
            $attendance->fill($newValues)->save();

            $after = $attendance->fresh()->only(array_keys($before));

            AttendanceCorrection::create([
                'attendance_id' => $attendance->id,
                'before'        => $before,
                'after'         => $after,
                'reason'        => $reason,
                'corrected_by'  => Auth::id(),
                'created_at'    => now(),
            ]);
            $this->audit->log('attendance.corrected', 'attendance', $attendance->id, $before, $after, $reason);

            return $attendance->fresh();
        });
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') return $branchId;
        return Auth::user()?->branch_id;
    }
}
