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

/*
 * Phase AB Round 4 — three digest variants:
 *   morning  07:30 daily   — forward-looking "what needs attention today"
 *   evening  18:00 daily   — backward-looking "how today went"
 *   weekly   Monday 07:30  — 7-day rollup with trend chart
 *
 * Each user can opt-in / out per variant via their preferences. The
 * service skips users who opted out, so the schedule doesn't need to.
 */
Schedule::call(function () {
    app(NotificationDigestService::class)->build(NotificationDigestService::PERIOD_MORNING);
})->dailyAt('07:30')->name('notif:digest:morning')->withoutOverlapping();

Schedule::call(function () {
    app(NotificationDigestService::class)->build(NotificationDigestService::PERIOD_EVENING);
})->dailyAt('18:00')->name('notif:digest:evening')->withoutOverlapping();

Schedule::call(function () {
    app(NotificationDigestService::class)->build(NotificationDigestService::PERIOD_WEEKLY);
})->weeklyOn(1, '07:30')->name('notif:digest:weekly')->withoutOverlapping();

Schedule::call(function () {
    app(AlertEscalationService::class)->pruneArchived(30);
})->dailyAt('03:00')->name('notif:prune')->withoutOverlapping();
