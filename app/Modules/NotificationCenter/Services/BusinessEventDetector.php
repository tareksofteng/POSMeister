<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Services\Detectors\AccountingDetector;
use App\Modules\NotificationCenter\Services\Detectors\CustomerDetector;
use App\Modules\NotificationCenter\Services\Detectors\FinanceDetector;
use App\Modules\NotificationCenter\Services\Detectors\HrmDetector;
use App\Modules\NotificationCenter\Services\Detectors\InventoryDetector;
use App\Modules\NotificationCenter\Services\Detectors\PurchaseDetector;
use App\Modules\NotificationCenter\Services\Detectors\SalesDetector;
use App\Modules\NotificationCenter\Services\Detectors\SupplierDetector;
use App\Modules\NotificationCenter\Services\Detectors\SystemOpsDetector;

/*
 |--------------------------------------------------------------------------
 | BusinessEventDetector — Phase AB orchestrator
 |--------------------------------------------------------------------------
 |
 | Runs every detector inside a try/catch wrapper so a single broken
 | domain (e.g. a renamed column on `journal_entries`) can't take down
 | the whole detection sweep. Each domain's result count surfaces in
 | the returned array — useful for the /api/notifications/detect manual
 | trigger and for tuning detector noise levels.
 |
 | Schedule: routes/console.php fires this every 10 minutes via
 |   Schedule::call(fn() => app(BusinessEventDetector::class)->detectAll())
 |     ->everyTenMinutes()->name('notif:detect')->withoutOverlapping();
 */
class BusinessEventDetector
{
    public function __construct(
        private InventoryDetector  $inventory,
        private SalesDetector      $sales,
        private PurchaseDetector   $purchase,
        private CustomerDetector   $customer,
        private SupplierDetector   $supplier,
        private FinanceDetector    $finance,
        private AccountingDetector $accounting,
        private HrmDetector        $hrm,
        private SystemOpsDetector  $systemOps,
    ) {}

    public function detectAll(): array
    {
        $started = microtime(true);

        $results = [
            'inventory'  => $this->safe(fn() => $this->inventory->run()),
            'sales'      => $this->safe(fn() => $this->sales->run()),
            'purchase'   => $this->safe(fn() => $this->purchase->run()),
            'customer'   => $this->safe(fn() => $this->customer->run()),
            'supplier'   => $this->safe(fn() => $this->supplier->run()),
            'finance'    => $this->safe(fn() => $this->finance->run()),
            'accounting' => $this->safe(fn() => $this->accounting->run()),
            'hrm'        => $this->safe(fn() => $this->hrm->run()),
            'system'     => $this->safe(fn() => $this->systemOps->run()),
        ];

        $results['ms']    = (int) round((microtime(true) - $started) * 1000);
        $results['total'] = array_sum(array_filter(
            $results,
            fn($v, $k) => is_int($v) && $k !== 'ms',
            ARRAY_FILTER_USE_BOTH,
        ));
        return $results;
    }

    /**
     * Per-detector failure isolation — one broken domain doesn't abort
     * the rest. The exception is reported to Laravel's error channel
     * (Sentry-compatible) for follow-up; the count returns 0 so the
     * caller still gets a clean integer.
     */
    private function safe(callable $fn): int
    {
        try   { return (int) $fn(); }
        catch (\Throwable $e) { report($e); return 0; }
    }
}
