<?php

namespace App\Modules\HRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Workforce + labour-cost analytics. Joins:
 *   employees ↔ users ↔ sales (cashier productivity)
 *   employees ↔ sale_returns (refund-risk)
 *   employees ↔ payslips (labour cost)
 *   branches ↔ sales (revenue per branch)
 *
 * All queries respect admin / manager branch scoping.
 */
class WorkforceAnalyticsService
{
    public const DEFAULT_DAYS = 30;

    public function dashboard(?int $branchId = null, int $lookbackDays = self::DEFAULT_DAYS): array
    {
        $scope = $this->resolveBranchScope($branchId);
        $from = Carbon::today()->subDays($lookbackDays)->toDateString();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();

        $activeEmployees = DB::table('employees')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->count();

        $revenue = (float) DB::table('sales')
            ->where('status', 'active')
            ->whereDate('sale_date', '>=', $monthStart)
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->sum('grand_total');

        $labourCost = (float) DB::table('payslips')
            ->where('status', 'paid')
            ->where('approval_status', 'approved')
            ->whereDate('payment_date', '>=', $monthStart)
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->sum('net_salary');

        $labourPct = $revenue > 0 ? round(($labourCost / $revenue) * 100, 1) : 0;
        $revenuePerHead = $activeEmployees > 0 ? round($revenue / $activeEmployees, 2) : 0;

        return [
            'as_of'             => Carbon::today()->toDateString(),
            'lookback_days'     => $lookbackDays,
            'active_employees'  => $activeEmployees,
            'revenue_month'     => round($revenue, 2),
            'labour_cost_month' => round($labourCost, 2),
            'labour_cost_pct'   => $labourPct,
            'revenue_per_head'  => $revenuePerHead,
            'top_cashiers'      => $this->salesPerCashier($scope, $from, 10),
            'refund_risk'       => $this->refundRisk($scope, $from, 10),
            'top_performers'    => $this->topPerformers($scope, $from, 10),
        ];
    }

    /**
     * For each cashier (user joined to employee), how many sales they
     * rang up, total revenue, average basket and refund rate.
     */
    public function salesPerCashier(?int $branchId, string $from, int $limit = 20): array
    {
        $rows = DB::table('sales as s')
            ->join('users as u', 'u.id', '=', 's.created_by')
            ->leftJoin('employees as e', 'e.user_id', '=', 'u.id')
            ->where('s.status', 'active')
            ->whereDate('s.sale_date', '>=', $from)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('
                u.id as user_id,
                u.name as user_name,
                e.id as employee_id,
                e.first_name, e.last_name,
                COUNT(*) as sales_count,
                COALESCE(SUM(s.grand_total), 0) as revenue,
                COALESCE(AVG(s.grand_total), 0) as avg_basket
            ')
            ->groupBy('u.id', 'u.name', 'e.id', 'e.first_name', 'e.last_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $userIds = $rows->pluck('user_id');
        $returnTotals = DB::table('sale_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->whereIn('s.created_by', $userIds)
            ->whereDate('r.return_date', '>=', $from)
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('s.created_by as user_id, COALESCE(SUM(r.refund_amount), 0) as refund_amount, COUNT(DISTINCT r.id) as refund_count')
            ->groupBy('s.created_by')
            ->get()->keyBy('user_id');

        return $rows->map(function ($r) use ($returnTotals) {
            $refund = $returnTotals[$r->user_id] ?? null;
            $refundAmount = (float) ($refund->refund_amount ?? 0);
            $refundCount  = (int)   ($refund->refund_count  ?? 0);
            $revenue = (float) $r->revenue;
            $refundRate = $revenue > 0 ? round(($refundAmount / $revenue) * 100, 1) : 0;
            return [
                'user_id'      => (int) $r->user_id,
                'employee_id'  => $r->employee_id ? (int) $r->employee_id : null,
                'name'         => $r->employee_id ? trim($r->first_name . ' ' . $r->last_name) : $r->user_name,
                'sales_count'  => (int) $r->sales_count,
                'revenue'      => round($revenue, 2),
                'avg_basket'   => round((float) $r->avg_basket, 2),
                'refund_count' => $refundCount,
                'refund_amount'=> round($refundAmount, 2),
                'refund_rate'  => $refundRate,
            ];
        })->all();
    }

    /**
     * Refund-risk view: cashiers with abnormally high refund-to-sales ratios.
     */
    public function refundRisk(?int $branchId, string $from, int $limit = 10): array
    {
        $rows = $this->salesPerCashier($branchId, $from, 200);
        usort($rows, fn($a, $b) => $b['refund_rate'] <=> $a['refund_rate']);
        return array_slice(array_filter($rows, fn($r) => $r['refund_count'] > 0), 0, $limit);
    }

    /**
     * Top performers — composite of revenue + attendance score.
     */
    public function topPerformers(?int $branchId, string $from, int $limit = 10): array
    {
        $cashiers = collect($this->salesPerCashier($branchId, $from, 200))
            ->whereNotNull('employee_id');

        $employeeIds = $cashiers->pluck('employee_id');

        $attendance = DB::table('attendance')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $from)
            ->selectRaw('
                employee_id,
                COUNT(*) as days,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_days,
                COALESCE(SUM(worked_minutes), 0) as worked_minutes
            ')
            ->groupBy('employee_id')
            ->get()->keyBy('employee_id');

        $scored = $cashiers->map(function ($c) use ($attendance) {
            $att = $attendance[$c['employee_id']] ?? null;
            $days = (int) ($att->days ?? 0);
            $present = (int) ($att->present_days ?? 0);
            $late = (int) ($att->late_days ?? 0);
            $hours = round(((int) ($att->worked_minutes ?? 0)) / 60, 1);
            $attendancePct = $days > 0 ? round(($present / $days) * 100, 1) : 0;
            $latePct       = $days > 0 ? round(($late    / $days) * 100, 1) : 0;

            // Composite score: revenue (cap normalised) - refund penalty + attendance bonus.
            $score = ($c['revenue'] / 1000)
                   - ($c['refund_rate'] * 2)
                   + ($attendancePct / 2)
                   - $latePct;

            return array_merge($c, [
                'present_days'   => $present,
                'late_days'      => $late,
                'worked_hours'   => $hours,
                'attendance_pct' => $attendancePct,
                'late_pct'       => $latePct,
                'score'          => round($score, 1),
            ]);
        });

        return $scored->sortByDesc('score')->take($limit)->values()->all();
    }

    /**
     * Branch staffing efficiency: revenue, labour cost, employee count.
     * Only admins get the multi-branch view.
     */
    public function branchEfficiency(string $from): array
    {
        if (Auth::user()?->role !== 'admin') {
            return [];
        }

        return DB::table('branches as b')
            ->leftJoinSub(
                DB::table('sales')->where('status', 'active')
                    ->whereDate('sale_date', '>=', $from)
                    ->selectRaw('branch_id, SUM(grand_total) as revenue'),
                's', 's.branch_id', '=', 'b.id'
            )
            ->leftJoinSub(
                DB::table('payslips')->where('status', 'paid')
                    ->where('approval_status', 'approved')
                    ->whereDate('payment_date', '>=', $from)
                    ->selectRaw('branch_id, SUM(net_salary) as labour'),
                'p', 'p.branch_id', '=', 'b.id'
            )
            ->leftJoinSub(
                DB::table('employees')->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->selectRaw('branch_id, COUNT(*) as headcount'),
                'e', 'e.branch_id', '=', 'b.id'
            )
            ->where('b.is_active', true)
            ->selectRaw('
                b.id, b.name,
                COALESCE(s.revenue, 0)  as revenue,
                COALESCE(p.labour, 0)   as labour,
                COALESCE(e.headcount,0) as headcount
            ')
            ->orderByDesc('s.revenue')
            ->get()
            ->map(function ($r) {
                $revenue = (float) $r->revenue;
                $labour  = (float) $r->labour;
                return [
                    'branch_id'        => (int) $r->id,
                    'name'             => $r->name,
                    'headcount'        => (int) $r->headcount,
                    'revenue'          => round($revenue, 2),
                    'labour_cost'      => round($labour, 2),
                    'labour_pct'       => $revenue > 0 ? round(($labour / $revenue) * 100, 1) : 0,
                    'revenue_per_head' => $r->headcount > 0 ? round($revenue / $r->headcount, 2) : 0,
                ];
            })->all();
    }

    /**
     * Employee utilisation: worked minutes / scheduled minutes for the
     * period. Requires shift hours to be configured.
     */
    public function utilisation(?int $branchId, string $from, string $to): array
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
                SUM(CASE WHEN a.status = "present" THEN 1 ELSE 0 END) as present_days,
                COALESCE(SUM(a.worked_minutes), 0) as worked_minutes
            ')
            ->groupBy('e.id', 'e.first_name', 'e.last_name', 'e.branch_id')
            ->orderByDesc('worked_minutes')
            ->limit(50)
            ->get()
            ->map(fn($r) => [
                'employee_id'  => (int) $r->id,
                'name'         => trim($r->first_name . ' ' . $r->last_name),
                'days'         => (int) $r->days,
                'present_days' => (int) $r->present_days,
                'worked_hours' => round(((int) $r->worked_minutes) / 60, 1),
                'utilisation'  => $r->days > 0 ? round(((int) $r->present_days / (int) $r->days) * 100, 1) : 0,
            ])->all();
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') return $branchId;
        return Auth::user()?->branch_id;
    }
}
