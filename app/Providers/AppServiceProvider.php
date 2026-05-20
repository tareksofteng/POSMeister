<?php

namespace App\Providers;

use App\Modules\Accounting\Observers\CustomerPaymentAccountingObserver;
use App\Modules\Accounting\Observers\ExpenseAccountingObserver;
use App\Modules\Accounting\Observers\PayslipAccountingObserver;
use App\Modules\Accounting\Observers\PurchaseAccountingObserver;
use App\Modules\Accounting\Observers\SaleAccountingObserver;
use App\Modules\Accounting\Observers\SupplierPaymentAccountingObserver;
use App\Modules\Expense\Models\Expense;
use App\Modules\HRM\Models\Payslip;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\CustomerPayment;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Accounting auto-posting hooks. Observers are idempotent and fail-safe:
        // a posting error is logged but never blocks the originating save.
        Sale::observe(SaleAccountingObserver::class);
        Purchase::observe(PurchaseAccountingObserver::class);
        Expense::observe(ExpenseAccountingObserver::class);
        Payslip::observe(PayslipAccountingObserver::class);
        CustomerPayment::observe(CustomerPaymentAccountingObserver::class);
        SupplierPayment::observe(SupplierPaymentAccountingObserver::class);
    }
}
