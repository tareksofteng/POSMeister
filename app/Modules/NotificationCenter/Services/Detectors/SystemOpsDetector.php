<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemOpsDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        $pushed = 0;
        $pushed += $this->backupStale();
        $pushed += $this->failedJobs();
        $pushed += $this->schedulerStalled();
        $pushed += $this->syncConflicts();
        return $pushed;
    }

    private function backupStale(): int
    {
        if (!Schema::hasTable('backup_runs')) return 0;
        $last = DB::table('backup_runs')->where('status', 'success')->max('finished_at');
        if (!$last) {
            $this->notify->push([
                'category'      => 'system',
                'code'          => 'system.backup_never',
                'severity'      => 'danger',
                'urgency'       => 80,
                'title'         => 'No successful backup recorded',
                'message'       => 'Run a backup from the SystemOps panel.',
                'audience_role' => 'admin',
                'dedupe_key'    => 'system.backup_never',
                'cooldown_minutes' => 1440,
                'actions'       => [['label' => 'systemOps.backup.runNow', 'route' => 'system-backup', 'type' => 'primary']],
            ]);
            return 1;
        }
        $ageH = now()->diffInHours($last);
        if ($ageH < 48) return 0;

        $this->notify->push([
            'category'      => 'system',
            'code'          => 'system.backup_stale',
            'severity'      => $ageH > 168 ? 'critical' : 'danger',
            'urgency'       => 85,
            'title'         => "Last successful backup is {$ageH}h old",
            'message'       => 'Run a backup to protect against data loss.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'system.backup_stale',
            'cooldown_minutes' => 720,
            'actions'       => [['label' => 'systemOps.backup.runNow', 'route' => 'system-backup', 'type' => 'primary']],
            'meta'          => ['age_hours' => $ageH],
        ]);
        return 1;
    }

    private function failedJobs(): int
    {
        if (!Schema::hasTable('failed_jobs')) return 0;
        $count = DB::table('failed_jobs')->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'system',
            'code'          => 'system.failed_jobs',
            'severity'      => $count > 50 ? 'critical' : 'warning',
            'urgency'       => 70,
            'title'         => "{$count} background job(s) failed",
            'message'       => 'Inspect the queue and retry or remove failures.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'system.failed_jobs',
            'cooldown_minutes' => 360,
            'actions'       => [['label' => 'systemOps.queue.title', 'route' => 'system-queue', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function schedulerStalled(): int
    {
        $last = Cache::get('sysops:scheduler:last_ping');
        if (!$last) return 0;  // never started — separate detection on first install
        $age = now()->diffInSeconds($last);
        if ($age < 600) return 0;  // 10min grace

        $this->notify->push([
            'category'      => 'system',
            'code'          => 'system.scheduler_stalled',
            'severity'      => $age > 3600 ? 'critical' : 'danger',
            'urgency'       => 90,
            'title'         => 'Scheduler is not running',
            'message'       => "Last heartbeat was {$age}s ago. Cron may have stopped.",
            'audience_role' => 'admin',
            'dedupe_key'    => 'system.scheduler_stalled',
            'cooldown_minutes' => 60,
            'actions'       => [['label' => 'systemOps.monitor.title', 'route' => 'system-monitor', 'type' => 'primary']],
            'meta'          => ['age_seconds' => $age],
        ]);
        return 1;
    }

    private function syncConflicts(): int
    {
        if (!Schema::hasTable('sync_conflicts')) return 0;
        $count = DB::table('sync_conflicts')->where('resolution', 'open')->count();
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'system',
            'code'          => 'system.sync_conflicts',
            'severity'      => 'warning',
            'urgency'       => 65,
            'title'         => "{$count} offline sync conflict(s) need review",
            'message'       => 'Open the sync recovery panel to resolve.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'system.sync_conflicts',
            'cooldown_minutes' => 720,
            'actions'       => [['label' => 'systemOps.sync.title', 'route' => 'system-sync', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }
}
