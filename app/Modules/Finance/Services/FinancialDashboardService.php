<?php

namespace App\Modules\Finance\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Expense\Models\Expense;
use App\Modules\HRM\Models\Payslip;
use App\Modules\Product\Models\Inventory;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    /**
     * Executive snapshot: KPIs + smart insights for the supplied period.
     * If $from/$to are null, defaults to "this month".
     */
    public function dashboard(?string $from = null, ?string $to = null, ?int $branchId = null): array
    {
        $from = $from ?: Carbon::today()->startOfMonth()->toDateString();
        $to   = $to   ?: Carbon::today()->endOfMonth()->toDateString();

        $kpis = $this->kpis($from, $to, $branchId);

        return [
            'period'   => ['from' => $from, 'to' => $to],
            'kpis'     => $kpis,
            'insights' => $this->insights($kpis, $from, $to, $branchId),
        ];
    }

    public function kpis(string $from, string $to, ?int $branchId = null): array
    {
        $totalSales      = $this->totalSales($from, $to, $branchId);
        $totalPurchases  = $this->totalPurchases($from, $to, $branchId);
        $cogs            = $this->cogs($from, $to, $branchId);
        $totalExpenses   = $this->totalExpenses($from, $to, $branchId);
        $payroll         = $this->payrollExpenses($from, $to, $branchId);
        $inventoryValue  = $this->inventoryValue($branchId);
        $receivables     = $this->outstandingReceivables($branchId);
        $payables        = $this->outstandingPayables($branchId);

        $grossProfit = $totalSales - $cogs;
        $netProfit   = $grossProfit - $totalExpenses - $payroll;

        return [
            'total_sales'             => round($totalSales, 2),
            'total_purchases'         => round($totalPurchases, 2),
            'cogs'                    => round($cogs, 2),
            'gross_profit'            => round($grossProfit, 2),
            'gross_margin_percent'    => $totalSales > 0 ? round($grossProfit / $totalSales * 100, 1) : 0,
            'total_expenses'          => round($totalExpenses, 2),
            'payroll_expenses'        => round($payroll, 2),
            'net_profit'              => round($netProfit, 2),
            'net_margin_percent'      => $totalSales > 0 ? round($netProfit / $totalSales * 100, 1) : 0,
            'inventory_value'         => round($inventoryValue, 2),
            'outstanding_receivables' => round($receivables, 2),
            'outstanding_payables'    => round($payables, 2),
            'revenue_growth_percent'  => $this->revenueGrowth($from, $to, $branchId),
        ];
    }

    /**
     * Monthly revenue + expenses for a fiscal year, used by the trend chart.
     */
    public function salesTrend(int $year, ?int $branchId = null): array
    {
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::createFromDate($year, $m, 1)->startOfMonth()->toDateString();
            $end   = Carbon::createFromDate($year, $m, 1)->endOfMonth()->toDateString();

            $sales    = $this->totalSales($start, $end, $branchId);
            $expenses = $this->totalExpenses($start, $end, $branchId) + $this->payrollExpenses($start, $end, $branchId);

            $rows[] = [
                'month'    => $m,
                'sales'    => round($sales, 2),
                'expenses' => round($expenses, 2),
                'net'      => round($sales - $expenses, 2),
            ];
        }

        return [
            'year' => $year,
            'data' => $rows,
        ];
    }

    /**
     * Profit waterfall — revenue → gross → operating → net, plus per-month profit.
     */
    public function profitAnalysis(int $year, ?int $branchId = null): array
    {
        $start = Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
        $end   = Carbon::createFromDate($year, 1, 1)->endOfYear()->toDateString();

        $revenue   = $this->totalSales($start, $end, $branchId);
        $cogs      = $this->cogs($start, $end, $branchId);
        $expenses  = $this->totalExpenses($start, $end, $branchId);
        $payroll   = $this->payrollExpenses($start, $end, $branchId);

        $gross     = $revenue - $cogs;
        $operating = $gross - $expenses - $payroll;
        $net       = $operating;

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $ms = Carbon::createFromDate($year, $m, 1)->startOfMonth()->toDateString();
            $me = Carbon::createFromDate($year, $m, 1)->endOfMonth()->toDateString();
            $r  = $this->totalSales($ms, $me, $branchId);
            $c  = $this->cogs($ms, $me, $branchId);
            $e  = $this->totalExpenses($ms, $me, $branchId) + $this->payrollExpenses($ms, $me, $branchId);
            $monthly[] = [
                'month'  => $m,
                'profit' => round($r - $c - $e, 2),
                'margin' => $r > 0 ? round(($r - $c - $e) / $r * 100, 1) : 0,
            ];
        }

        return [
            'year' => $year,
            'waterfall' => [
                ['label' => 'revenue',       'value' => round($revenue, 2)],
                ['label' => 'cogs',          'value' => round(-$cogs, 2)],
                ['label' => 'gross_profit',  'value' => round($gross, 2)],
                ['label' => 'expenses',      'value' => round(-$expenses, 2)],
                ['label' => 'payroll',       'value' => round(-$payroll, 2)],
                ['label' => 'net_profit',    'value' => round($net, 2)],
            ],
            'monthly' => $monthly,
        ];
    }

    public function branchPerformance(string $from, string $to): array
    {
        if (!$this->isAdmin()) {
            return [];
        }

        return Branch::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'code'])
            ->map(function ($branch) use ($from, $to) {
                $sales    = $this->totalSales($from, $to, $branch->id);
                $cogs     = $this->cogs($from, $to, $branch->id);
                $expenses = $this->totalExpenses($from, $to, $branch->id)
                          + $this->payrollExpenses($from, $to, $branch->id);
                $profit   = $sales - $cogs - $expenses;
                return [
                    'branch_id'   => $branch->id,
                    'branch_name' => $branch->name,
                    'sales'       => round($sales, 2),
                    'expenses'    => round($expenses, 2),
                    'profit'      => round($profit, 2),
                    'margin'      => $sales > 0 ? round($profit / $sales * 100, 1) : 0,
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    public function topProducts(string $from, string $to, ?int $branchId = null, int $limit = 10): array
    {
        $q = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->where('sales.status', 'active');

        if ($branchId)          $q->where('sales.branch_id', $branchId);
        if (!$this->isAdmin())  $q->where('sales.branch_id', Auth::user()->branch_id);

        return $q->selectRaw('
                products.id as product_id,
                products.name,
                products.sku,
                SUM(sale_items.quantity) as qty_sold,
                SUM(sale_items.line_total) as revenue,
                SUM(sale_items.quantity * sale_items.cost_price) as cost_total
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'product_id' => (int) $r->product_id,
                'name'       => $r->name,
                'sku'        => $r->sku,
                'qty_sold'   => round((float) $r->qty_sold, 2),
                'revenue'    => round((float) $r->revenue, 2),
                'profit'     => round((float) $r->revenue - (float) $r->cost_total, 2),
                'margin'     => (float) $r->revenue > 0
                    ? round(((float) $r->revenue - (float) $r->cost_total) / (float) $r->revenue * 100, 1)
                    : 0,
            ])
            ->all();
    }

    public function topCustomers(string $from, string $to, ?int $branchId = null, int $limit = 10): array
    {
        $q = Sale::query()
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'active')
            ->whereNotNull('customer_id');

        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);

        $rows = $q->selectRaw('
                customer_id,
                COUNT(*) as invoice_count,
                SUM(grand_total) as revenue,
                SUM(due_amount) as outstanding
            ')
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $customerNames = Customer::whereIn('id', $rows->pluck('customer_id'))->pluck('name', 'id');

        return $rows->map(fn($r) => [
            'customer_id'   => (int) $r->customer_id,
            'name'          => $customerNames[$r->customer_id] ?? '—',
            'invoice_count' => (int) $r->invoice_count,
            'revenue'       => round((float) $r->revenue, 2),
            'outstanding'   => round((float) $r->outstanding, 2),
        ])->all();
    }

    public function expenseBreakdown(string $from, string $to, ?int $branchId = null): array
    {
        $q = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->whereIn('expenses.status', ['approved', 'paid']);

        if ($branchId)         $q->where('expenses.branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('expenses.branch_id', Auth::user()->branch_id);

        $rows = $q->selectRaw('
                expense_categories.id as category_id,
                expense_categories.name,
                SUM(expenses.amount) as total
            ')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get();

        $grand = (float) $rows->sum('total');

        return $rows->map(fn($r) => [
            'category_id' => (int) $r->category_id,
            'name'        => $r->name,
            'amount'      => round((float) $r->total, 2),
            'percent'     => $grand > 0 ? round((float) $r->total / $grand * 100, 1) : 0,
        ])->all();
    }

    public function inventoryInsights(?int $branchId = null): array
    {
        $invQ = Inventory::query()
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->where('products.is_active', true);

        if ($branchId)         $invQ->where('inventory.branch_id', $branchId);
        if (!$this->isAdmin()) $invQ->where('inventory.branch_id', Auth::user()->branch_id);

        $summary = (clone $invQ)->selectRaw('
            COALESCE(SUM(inventory.quantity * products.cost_price), 0) as stock_value,
            COALESCE(SUM(inventory.quantity), 0) as stock_units
        ')->first();

        $lowStock = (clone $invQ)
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('products.reorder_level', '>', 0)
            ->selectRaw('products.id, products.name, products.sku, inventory.quantity, products.reorder_level')
            ->orderBy('inventory.quantity')
            ->limit(10)
            ->get();

        $outOfStock = (clone $invQ)
            ->where('inventory.quantity', '<=', 0)
            ->selectRaw('products.id, products.name, products.sku')
            ->limit(10)
            ->get();

        return [
            'stock_value'         => round((float) $summary->stock_value, 2),
            'stock_units'         => round((float) $summary->stock_units, 2),
            'low_stock_count'     => $lowStock->count(),
            'out_of_stock_count'  => $outOfStock->count(),
            'low_stock_items'     => $lowStock->map(fn($r) => [
                'product_id'    => (int) $r->id,
                'name'          => $r->name,
                'sku'           => $r->sku,
                'quantity'      => round((float) $r->quantity, 2),
                'reorder_level' => round((float) $r->reorder_level, 2),
            ])->all(),
            'out_of_stock_items'  => $outOfStock->map(fn($r) => [
                'product_id' => (int) $r->id,
                'name'       => $r->name,
                'sku'        => $r->sku,
            ])->all(),
        ];
    }

    // --- KPI building blocks -------------------------------------------------

    private function totalSales(string $from, string $to, ?int $branchId): float
    {
        return (float) $this->scopeSales($branchId)
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', 'active')
            ->sum('grand_total');
    }

    private function totalPurchases(string $from, string $to, ?int $branchId): float
    {
        return (float) $this->scopePurchases($branchId)
            ->whereBetween('purchase_date', [$from, $to])
            ->where('status', 'received')
            ->sum('total_amount');
    }

    private function cogs(string $from, string $to, ?int $branchId): float
    {
        $q = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->where('sales.status', 'active');

        if ($branchId)         $q->where('sales.branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('sales.branch_id', Auth::user()->branch_id);

        return (float) $q->sum(DB::raw('sale_items.quantity * sale_items.cost_price'));
    }

    private function totalExpenses(string $from, string $to, ?int $branchId): float
    {
        return (float) $this->scopeExpenses($branchId)
            ->whereBetween('expense_date', [$from, $to])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');
    }

    private function payrollExpenses(string $from, string $to, ?int $branchId): float
    {
        return (float) $this->scopePayslips($branchId)
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$from, $to])
            ->sum('net_salary');
    }

    private function inventoryValue(?int $branchId): float
    {
        $q = Inventory::query()
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->where('products.is_active', true);

        if ($branchId)         $q->where('inventory.branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('inventory.branch_id', Auth::user()->branch_id);

        return (float) $q->sum(DB::raw('inventory.quantity * products.cost_price'));
    }

    private function outstandingReceivables(?int $branchId): float
    {
        return (float) $this->scopeSales($branchId)
            ->where('status', 'active')
            ->sum('due_amount');
    }

    /**
     * Total unpaid purchases — sum of purchase totals minus supplier payments
     * (open balance, supplier-based ledger view).
     */
    private function outstandingPayables(?int $branchId): float
    {
        $purchased = (float) $this->scopePurchases($branchId)
            ->where('status', 'received')
            ->sum('total_amount');

        $paid = (float) $this->scopeSupplierPayments($branchId)->sum('amount');

        return max(0, $purchased - $paid);
    }

    private function revenueGrowth(string $from, string $to, ?int $branchId): float
    {
        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);
        $days  = $start->diffInDays($end);

        $prevStart = $start->copy()->subDays($days + 1);
        $prevEnd   = $start->copy()->subDay();

        $thisRevenue = $this->totalSales($from, $to, $branchId);
        $prevRevenue = $this->totalSales($prevStart->toDateString(), $prevEnd->toDateString(), $branchId);

        if ($prevRevenue <= 0) {
            return $thisRevenue > 0 ? 100.0 : 0.0;
        }

        return round(($thisRevenue - $prevRevenue) / $prevRevenue * 100, 1);
    }

    // --- smart insights ------------------------------------------------------

    /**
     * Plain-language observations derived from the current KPIs. Each insight
     * has a tone (positive | warning | critical | info) so the UI can colour-code.
     */
    private function insights(array $kpis, string $from, string $to, ?int $branchId): array
    {
        $out = [];

        if ($kpis['net_profit'] > 0) {
            $out[] = [
                'tone' => 'positive',
                'text' => 'Profitabel im Zeitraum: Nettogewinn ' . $this->fmt($kpis['net_profit'])
                    . ' (Marge ' . $kpis['net_margin_percent'] . '%).',
            ];
        } elseif ($kpis['net_profit'] < 0) {
            $out[] = [
                'tone' => 'critical',
                'text' => 'Verlust im Zeitraum: ' . $this->fmt($kpis['net_profit'])
                    . '. Ausgaben überprüfen.',
            ];
        }

        if ($kpis['revenue_growth_percent'] >= 10) {
            $out[] = [
                'tone' => 'positive',
                'text' => 'Umsatzwachstum +' . $kpis['revenue_growth_percent'] . '% gegenüber Vorperiode.',
            ];
        } elseif ($kpis['revenue_growth_percent'] <= -10) {
            $out[] = [
                'tone' => 'warning',
                'text' => 'Umsatzrückgang ' . $kpis['revenue_growth_percent'] . '% gegenüber Vorperiode.',
            ];
        }

        if ($kpis['gross_margin_percent'] < 20 && $kpis['total_sales'] > 0) {
            $out[] = [
                'tone' => 'warning',
                'text' => 'Bruttomarge nur ' . $kpis['gross_margin_percent'] . '% — Einkaufspreise prüfen.',
            ];
        }

        if ($kpis['outstanding_receivables'] > 0 && $kpis['total_sales'] > 0) {
            $ratio = $kpis['outstanding_receivables'] / $kpis['total_sales'] * 100;
            if ($ratio > 30) {
                $out[] = [
                    'tone' => 'warning',
                    'text' => 'Hohe Außenstände: ' . $this->fmt($kpis['outstanding_receivables'])
                        . ' (' . round($ratio, 0) . '% des Umsatzes).',
                ];
            }
        }

        $totalCost = $kpis['cogs'] + $kpis['total_expenses'] + $kpis['payroll_expenses'];
        if ($totalCost > 0 && $kpis['payroll_expenses'] / $totalCost > 0.5) {
            $out[] = [
                'tone' => 'info',
                'text' => 'Personalkosten machen über 50% der Gesamtkosten aus.',
            ];
        }

        $inv = $this->inventoryInsights($branchId);
        if ($inv['out_of_stock_count'] > 0) {
            $out[] = [
                'tone' => 'critical',
                'text' => $inv['out_of_stock_count'] . ' Artikel ohne Bestand — Nachbestellung erforderlich.',
            ];
        } elseif ($inv['low_stock_count'] >= 3) {
            $out[] = [
                'tone' => 'warning',
                'text' => $inv['low_stock_count'] . ' Artikel unter Meldebestand.',
            ];
        }

        return $out;
    }

    // --- scoped query helpers -----------------------------------------------

    private function scopeSales(?int $branchId)
    {
        $q = Sale::query();
        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);
        return $q;
    }

    private function scopePurchases(?int $branchId)
    {
        $q = Purchase::query();
        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);
        return $q;
    }

    private function scopeExpenses(?int $branchId)
    {
        $q = Expense::query();
        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);
        return $q;
    }

    private function scopePayslips(?int $branchId)
    {
        $q = Payslip::query();
        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);
        return $q;
    }

    private function scopeSupplierPayments(?int $branchId)
    {
        $q = SupplierPayment::query();
        if ($branchId)         $q->where('branch_id', $branchId);
        if (!$this->isAdmin()) $q->where('branch_id', Auth::user()->branch_id);
        return $q;
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    private function fmt(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }
}
