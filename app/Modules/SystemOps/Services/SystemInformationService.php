<?php

namespace App\Modules\SystemOps\Services;

/**
 * Aggregates the SystemOps subservices into one snapshot for the
 * system dashboard. Keeps the controller layer thin — the view
 * makes one HTTP call and gets everything it needs.
 */
class SystemInformationService
{
    public function __construct(
        private EnvironmentValidationService  $env,
        private QueueDiagnosticsService       $queue,
        private SchedulerDiagnosticsService   $scheduler,
        private BackupService                 $backup,
        private DeploymentService             $deployment,
        private OfflineSyncService            $sync,
    ) {}

    public function dashboard(): array
    {
        $env       = $this->env->run();
        $queue     = $this->queue->snapshot();
        $scheduler = $this->scheduler->snapshot();
        $backup    = $this->backup->summary();
        $sync      = $this->sync->summary();

        return [
            'as_of'         => now()->toIso8601String(),
            'deployment'    => $this->deployment->info(),
            'health_score'  => $this->score($env, $queue, $scheduler, $backup),
            'environment'   => $env,
            'queue'         => $queue,
            'scheduler'     => $scheduler,
            'backup'        => $backup,
            'sync'          => $sync,
            'storage_usage' => $this->storageUsage(),
        ];
    }

    private function score(array $env, array $queue, array $scheduler, array $backup): array
    {
        $points = 100;
        $reasons = [];

        if (!($env['database']['ok'] ?? false))   { $points -= 40; $reasons[] = 'Database unreachable'; }
        if (!($env['cache']['ok']    ?? false))   { $points -= 10; $reasons[] = 'Cache failing'; }
        if (!($env['security']['ok'] ?? false))   { $points -= 15; $reasons[] = 'Security config'; }
        if (!($env['storage']['ok']  ?? false))   { $points -= 10; $reasons[] = 'Storage permissions'; }

        if (($queue['failed'] ?? 0) > 10)         { $points -= 10; $reasons[] = "{$queue['failed']} failed jobs"; }
        if (($scheduler['status'] ?? '') === 'critical') { $points -= 15; $reasons[] = 'Scheduler not running'; }
        elseif (($scheduler['status'] ?? '') === 'warning') { $points -= 5; }

        if (empty($backup['last_success_at']))    { $points -= 10; $reasons[] = 'No successful backup yet'; }

        $points = max($points, 0);
        $grade = $points >= 90 ? 'excellent' : ($points >= 75 ? 'good' : ($points >= 50 ? 'attention' : 'critical'));

        return ['points' => $points, 'grade' => $grade, 'reasons' => $reasons];
    }

    private function storageUsage(): array
    {
        $root = storage_path();
        $total = @disk_total_space($root) ?: 0;
        $free  = @disk_free_space($root) ?: 0;
        $used  = max($total - $free, 0);

        return [
            'total_bytes' => $total,
            'free_bytes'  => $free,
            'used_bytes'  => $used,
            'used_pct'    => $total > 0 ? round($used / $total * 100, 1) : 0,
        ];
    }
}
