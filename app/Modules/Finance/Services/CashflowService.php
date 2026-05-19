<?php

namespace App\Modules\Finance\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\HRM\Models\Payslip;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\CustomerPayment;
use App\Modules\Sales\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CashflowService
{
    /**
     * Cash-basis dashboard for a date range. Inflow = money actually received
     * (POS payments + customer payment receipts). Outflow = money actually
     * sent out (paid expenses + supplier payments + paid payslips).
     */
    public function dashboard(?string $from = null, ?string $to = null, ?int $branchId = null): array
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd   = Carbon::today()->endOfMonth();

        $from = $from ?: $monthStart->toDateString();
        $to   = $to   ?: $monthEnd->toDateString();

        return [
            'period'           => ['from' => $from, 'to' => $to],
            'inflow'           => $this->inflowTotals($from, $to, $branchId),
            'outflow'          => $this->outflowTotals($from, $to, $branchId),
            'net'              => $this->net($from, $to, $branchId),
            'monthly_trend'    => $this->monthlyTrend(Carbon::parse($from)->year, $branchId),
            'branch_breakdown' => $this->branchBreakdown($from, $to),
        ];
    }

    public function monthlyTrend(int $year, ?int $branchId = null): array
    {
        $rows = [];

        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::createFromDate($year, $m, 1)->startOfMonth();
            $end   = (clone $start)->endOfMonth();
            $in  = $this->totalInflow($start, $end, $branchId);
            $out = $this->totalOutflow($start, $end, $branchId);
            $rows[] = [
                'month'   => $m,
                'inflow'  => round($in, 2),
                'outflow' => round($out, 2),
                'net'     => round($in - $out, 2),
            ];
        }
        return $rows;
    }

    public function branchBreakdown(string $from, string $to): array
    {
        if (!$this->isAdmin()) {
            return [];
        }

        // Build a per-branch summary by iterating known branch ids encountered
        $branchIds = collect()
            ->merge(Sale::whereBetween('sale_date', [$from, $to])->where('status', 'active')->pluck('branch_id'))
            ->merge(Expense::whereBetween('expense_date', [$from, $to])->where('status', 'paid')->pluck('branch_id'))
            ->unique()->filter()->values();

        $out = [];
        foreach ($branchIds as $bid) {
            $in  = $this->totalInflow(Carbon::parse($from), Carbon::parse($to), $bid);
            $exp = $this->totalOutflow(Carbon::parse($from), Carbon::parse($to), $bid);
            $branchName = \App\Modules\Branch\Models\Branch::where('id', $bid)->value('name') ?? '—';
            $out[] = [
                'branch_id' => $bid,
                'branch_name' => $branchName,
                'inflow'  => round($in, 2),
                'outflow' => round($exp, 2),
                'net'     => round($in - $exp, 2),
            ];
        }
        usort($out, fn($a, $b) => $b['net'] <=> $a['net']);
        return $out;
    }

    public function forecast(int $lookbackMonths = 3, ?int $branchId = null): array
    {
        $end   = Carbon::today()->subDay()->endOfMonth();
        $start = (clone $end)->subMonthsNoOverflow($lookbackMonths - 1)->startOfMonth();

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->whereIn('status', ['approved', 'paid'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->selectRaw('expense_category_id, sum(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $expenseTotal = (float) $expenses->sum();
        $avgPerMonth  = $lookbackMonths > 0 ? $expenseTotal / $lookbackMonths : 0;

        $payrollAvg = Payslip::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->avg('net_salary') ?: 0;

        $categories = $expenses->map(function ($total, $catId) use ($lookbackMonths) {
            $monthlyAvg = $lookbackMonths > 0 ? (float) $total / $lookbackMonths : 0;
            $catName = \App\Modules\Expense\Models\ExpenseCategory::where('id', $catId)->value('name') ?? '—';
            return [
                'expense_category_id' => (int) $catId,
                'category_name'       => $catName,
                'predicted_next_month'=> round($monthlyAvg, 2),
            ];
        })->values();

        return [
            'lookback_months'             => $lookbackMonths,
            'period'                      => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'predicted_expenses_next_mo'  => round($avgPerMonth, 2),
            'predicted_payroll_next_mo'   => round((float) $payrollAvg, 2),
            'predicted_total_next_mo'     => round($avgPerMonth + (float) $payrollAvg, 2),
            'by_category'                 => $categories,
        ];
    }

    // ---- private helpers ------------------------------------------------

    private function inflowTotals(string $from, string $to, ?int $branchId): array
    {
        $sale = Sale::whereBetween('sale_date', [$from, $to])
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->selectRaw('coalesce(sum(cash_paid), 0) as cash, coalesce(sum(card_paid), 0) as card')
            ->first();

        $customer = CustomerPayment::whereBetween('payment_date', [$from, $to])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->sum('amount');

        return [
            'sale_cash'         => round((float) $sale->cash, 2),
            'sale_card'         => round((float) $sale->card, 2),
            'customer_payments' => round((float) $customer, 2),
            'total'             => round((float) $sale->cash + (float) $sale->card + (float) $customer, 2),
        ];
    }

    private function outflowTotals(string $from, string $to, ?int $branchId): array
    {
        $expenses = Expense::whereBetween('expense_date', [$from, $to])
            ->where('status', 'paid')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->sum('amount');

        $supplier = SupplierPayment::whereBetween('payment_date', [$from, $to])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->sum('amount');

        $payroll = Payslip::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when(!$this->isAdmin(), fn($q) => $q->where('branch_id', Auth::user()->branch_id))
            ->sum('net_salary');

        return [
            'expenses' => round((float) $expenses, 2),
            'supplier_payments' => round((float) $supplier, 2),
            'payroll' => round((float) $payroll, 2),
            'total'   => round((float) $expenses + (float) $supplier + (float) $payroll, 2),
        ];
    }

    private function net(string $from, string $to, ?int $branchId): array
    {
        $in  = $this->inflowTotals($from, $to, $branchId)['total'];
        $out = $this->outflowTotals($from, $to, $branchId)['total'];
        return [
            'amount' => round($in - $out, 2),
            'health' => $in >= $out ? 'positive' : 'negative',
        ];
    }

    private function totalInflow(Carbon $start, Carbon $end, ?int $branchId): float
    {
        return $this->inflowTotals($start->toDateString(), $end->toDateString(), $branchId)['total'];
    }

    private function totalOutflow(Carbon $start, Carbon $end, ?int $branchId): float
    {
        return $this->outflowTotals($start->toDateString(), $end->toDateString(), $branchId)['total'];
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
