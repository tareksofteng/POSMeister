<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Services\Detectors\FinanceDetector;
use App\Modules\NotificationCenter\Services\Detectors\HrmDetector;
use App\Modules\NotificationCenter\Services\Detectors\InventoryDetector;
use App\Modules\NotificationCenter\Services\Detectors\SalesDetector;
use App\Modules\NotificationCenter\Services\Detectors\SystemOpsDetector;

class BusinessEventDetector
{
    public function __construct(
        private InventoryDetector $inventory,
        private SalesDetector     $sales,
        private FinanceDetector   $finance,
        private HrmDetector       $hrm,
        private SystemOpsDetector $systemOps,
    ) {}

    public function detectAll(): array
    {
        $started = microtime(true);
        $results = [
            'inventory' => $this->safe(fn() => $this->inventory->run()),
            'sales'     => $this->safe(fn() => $this->sales->run()),
            'finance'   => $this->safe(fn() => $this->finance->run()),
            'hrm'       => $this->safe(fn() => $this->hrm->run()),
            'system'    => $this->safe(fn() => $this->systemOps->run()),
        ];
        $results['ms']    = (int) round((microtime(true) - $started) * 1000);
        $results['total'] = array_sum(array_filter($results, fn($v, $k) => is_int($v) && $k !== 'ms', ARRAY_FILTER_USE_BOTH));
        return $results;
    }

    private function safe(callable $fn): int
    {
        try   { return (int) $fn(); }
        catch (\Throwable $e) { report($e); return 0; }
    }
}
