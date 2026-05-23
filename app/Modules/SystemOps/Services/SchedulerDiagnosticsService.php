<?php

namespace App\Modules\SystemOps\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Scheduler heartbeat tracking. The actual heartbeat is written by the
 * scheduler itself (see App\Console\Kernel::schedule()), so this service
 * is purely a read view. Anything older than the warning threshold means
 * cron has likely stopped firing `php artisan schedule:run`.
 */
class SchedulerDiagnosticsService
{
    const HEARTBEAT_KEY = 'sysops:scheduler:last_ping';
    const WARN_AFTER_SECONDS = 120;
    const FAIL_AFTER_SECONDS = 600;

    public function snapshot(): array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        $age  = $last ? now()->diffInSeconds($last) : null;

        $status = 'unknown';
        if ($last) {
            if ($age <= self::WARN_AFTER_SECONDS)   $status = 'ok';
            elseif ($age <= self::FAIL_AFTER_SECONDS) $status = 'warning';
            else $status = 'critical';
        }

        return [
            'last_ping'     => $last,
            'age_seconds'   => $age,
            'status'        => $status,
            'warn_after'    => self::WARN_AFTER_SECONDS,
            'fail_after'    => self::FAIL_AFTER_SECONDS,
            'cron_hint'     => '* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1',
        ];
    }

    public function recordHeartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->toIso8601String(), now()->addHour());
    }
}
