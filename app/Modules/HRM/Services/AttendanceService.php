<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    private const ALLOWED_STATUSES = ['present', 'absent', 'leave', 'late', 'half_day'];

    /**
     * Daily sheet: every active employee in scope, joined with whatever attendance
     * record exists for the chosen date. Missing rows are returned as null so the
     * UI can render an empty slot to fill in.
     */
    public function dailySheet(string $date, ?int $branchId = null): array
    {
        $employees = Employee::with(['shift:id,name,start_time,end_time,grace_minutes', 'department:id,name'])
            ->where('status', 'active');

        $this->applyBranchScope($employees, $branchId);

        $employees = $employees->orderBy('first_name')->orderBy('last_name')->get();

        $records = Attendance::whereDate('attendance_date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $rows = $employees->map(function (Employee $e) use ($records) {
            $rec = $records->get($e->id);
            return [
                'employee_id'     => $e->id,
                'employee_code'   => $e->employee_id,
                'employee_name'   => $e->full_name,
                'department'      => $e->department?->name,
                'photo_url'       => $e->photo ? \Storage::url($e->photo) : null,
                'shift_name'      => $e->shift?->name,
                'shift_start'     => $e->shift ? substr($e->shift->start_time, 0, 5) : null,
                'shift_end'       => $e->shift ? substr($e->shift->end_time,   0, 5) : null,

                'attendance_id'   => $rec?->id,
                'status'          => $rec?->status,
                'check_in'        => $rec?->check_in ? substr($rec->check_in, 0, 5) : null,
                'check_out'       => $rec?->check_out ? substr($rec->check_out, 0, 5) : null,
                'is_late'         => (bool) ($rec?->is_late ?? false),
                'remarks'         => $rec?->remarks,
            ];
        });

        return [
            'date'  => $date,
            'rows'  => $rows->values(),
            'stats' => $this->stats($rows),
        ];
    }

    /**
     * Persist one or many attendance entries in a single transaction. Each row
     * upserts on (employee_id, attendance_date) so it works for create and edit
     * without the caller having to know which it is.
     */
    public function bulkMark(string $date, array $rows): array
    {
        $userId = Auth::id();

        DB::transaction(function () use ($date, $rows, $userId) {
            foreach ($rows as $row) {
                if (empty($row['employee_id']) || empty($row['status'])) {
                    continue;
                }
                if (!in_array($row['status'], self::ALLOWED_STATUSES, true)) {
                    continue;
                }

                $employee = Employee::with('shift')->find($row['employee_id']);
                if (!$employee) {
                    continue;
                }

                $status   = $row['status'];
                $checkIn  = $this->normalizeTime($row['check_in']  ?? null);
                $checkOut = $this->normalizeTime($row['check_out'] ?? null);
                $isLate   = $this->detectLate($employee, $status, $checkIn);

                // promote present -> late automatically when check-in is past grace
                if ($status === 'present' && $isLate) {
                    $status = 'late';
                }

                $worked = $this->workedMinutes($checkIn, $checkOut);

                Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'attendance_date' => $date],
                    [
                        'branch_id'       => $employee->branch_id,
                        'shift_id'        => $employee->shift_id,
                        'status'          => $status,
                        'check_in'        => $checkIn,
                        'check_out'       => $checkOut,
                        'worked_minutes'  => $worked,
                        'is_late'         => $isLate,
                        'remarks'         => $row['remarks'] ?? null,
                        'created_by'      => $userId,
                    ]
                );
            }
        });

        return $this->dailySheet($date);
    }

    /**
     * Monthly matrix: each row is an employee, plus a `days` array indexed 1..N
     * containing the per-day status code (or null). Used by the read-only
     * monthly report screen.
     */
    public function monthlyMatrix(int $year, int $month, ?int $branchId = null, ?int $departmentId = null): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $daysInMonth = $end->day;

        $employees = Employee::with('department:id,name')
            ->where('status', 'active');

        if ($departmentId) {
            $employees->where('department_id', $departmentId);
        }
        $this->applyBranchScope($employees, $branchId);

        $employees = $employees->orderBy('first_name')->orderBy('last_name')->get();

        $rawAttendance = Attendance::whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'attendance_date', 'status', 'is_late']);

        // Index attendance by employee + day-of-month
        $byEmpDay = [];
        foreach ($rawAttendance as $a) {
            $d = Carbon::parse($a->attendance_date)->day;
            $byEmpDay[$a->employee_id][$d] = [
                'status'  => $a->status,
                'is_late' => (bool) $a->is_late,
            ];
        }

        $rows = $employees->map(function (Employee $e) use ($byEmpDay, $daysInMonth) {
            $days = [];
            $summary = ['present' => 0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'half_day' => 0];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $entry = $byEmpDay[$e->id][$d] ?? null;
                $days[$d] = $entry;
                if ($entry) {
                    $summary[$entry['status']] = ($summary[$entry['status']] ?? 0) + 1;
                }
            }
            return [
                'employee_id'   => $e->id,
                'employee_code' => $e->employee_id,
                'employee_name' => $e->full_name,
                'department'    => $e->department?->name,
                'days'          => $days,
                'summary'       => $summary,
            ];
        });

        return [
            'year'         => $year,
            'month'        => $month,
            'days_in_month'=> $daysInMonth,
            'rows'         => $rows->values(),
        ];
    }

    public function destroy(Attendance $attendance): void
    {
        $attendance->delete();
    }

    private function detectLate(Employee $employee, string $status, ?string $checkIn): bool
    {
        if ($status === 'late') {
            return true;
        }
        if (!$checkIn || !$employee->shift) {
            return false;
        }

        $shiftStart = Carbon::parse($employee->shift->start_time);
        $grace      = (int) ($employee->shift->grace_minutes ?? 0);
        $threshold  = $shiftStart->copy()->addMinutes($grace);

        $arrival = Carbon::parse($checkIn);

        return $arrival->greaterThan($threshold);
    }

    private function workedMinutes(?string $in, ?string $out): ?int
    {
        if (!$in || !$out) {
            return null;
        }
        $start = Carbon::parse($in);
        $end   = Carbon::parse($out);

        // Handle overnight shifts: if check_out is earlier than check_in, assume next day
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    private function normalizeTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }
        // Accept HH:mm or HH:mm:ss; always store as HH:mm:00
        $parts = explode(':', $time);
        $h = str_pad((string) (int) ($parts[0] ?? '00'), 2, '0', STR_PAD_LEFT);
        $m = str_pad((string) (int) ($parts[1] ?? '00'), 2, '0', STR_PAD_LEFT);
        return "{$h}:{$m}:00";
    }

    private function stats(Collection $rows): array
    {
        $stats = [
            'total'    => $rows->count(),
            'marked'   => 0,
            'present'  => 0,
            'absent'   => 0,
            'leave'    => 0,
            'late'     => 0,
            'half_day' => 0,
        ];
        foreach ($rows as $r) {
            if ($r['status']) {
                $stats['marked']++;
                $stats[$r['status']] = ($stats[$r['status']] ?? 0) + 1;
            }
        }
        $stats['unmarked'] = $stats['total'] - $stats['marked'];
        return $stats;
    }

    private function applyBranchScope($query, ?int $branchId): void
    {
        $user = Auth::user();
        if ($this->isAdmin() && $branchId) {
            $query->where('branch_id', $branchId);
            return;
        }
        if (!$this->isAdmin() && $user?->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
