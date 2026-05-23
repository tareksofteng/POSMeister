<?php

use App\Modules\SystemOps\Services\OfflineSyncService;
use App\Modules\SystemOps\Services\SchedulerDiagnosticsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('expenses:generate-recurring')->dailyAt('00:30');

/*
 * SystemOps heartbeat — proves cron is actually wired up. The dashboard
 * reads this timestamp and turns red if it stops advancing.
 */
Schedule::call(function () {
    app(SchedulerDiagnosticsService::class)->recordHeartbeat();
})->everyMinute()->name('sysops:heartbeat');

Schedule::call(function () {
    app(OfflineSyncService::class)->prune(168);
})->dailyAt('02:30')->name('sysops:prune-idempotency');
