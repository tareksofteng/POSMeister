<?php

use App\Modules\NotificationCenter\Services\AlertEscalationService;
use App\Modules\NotificationCenter\Services\BusinessEventDetector;
use App\Modules\NotificationCenter\Services\NotificationDigestService;
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

/*
 * ── Phase Ω+ — Smart Notification Center ──────────────────────────────
 *   detectAll  : every 10 minutes — checks all 5 business domains
 *   escalate   : every hour       — promotes ageing alerts
 *   digest     : daily 07:30      — builds per-user daily summary
 */
Schedule::call(function () {
    app(BusinessEventDetector::class)->detectAll();
})->everyTenMinutes()->name('notif:detect')->withoutOverlapping();

Schedule::call(function () {
    app(AlertEscalationService::class)->run();
})->hourly()->name('notif:escalate')->withoutOverlapping();

Schedule::call(function () {
    app(NotificationDigestService::class)->buildDaily();
})->dailyAt('07:30')->name('notif:digest')->withoutOverlapping();

Schedule::call(function () {
    app(AlertEscalationService::class)->pruneArchived(30);
})->dailyAt('03:00')->name('notif:prune')->withoutOverlapping();
