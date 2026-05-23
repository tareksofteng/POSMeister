<?php

namespace App\Modules\SystemOps\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only diagnostics for the Laravel queue. Works for the database
 * driver out of the box (looking at the `jobs` and `failed_jobs` tables)
 * and reports as much as it safely can for other drivers.
 */
class QueueDiagnosticsService
{
    public function snapshot(): array
    {
        $driver = config('queue.default');
        $base = [
            'driver'   => $driver,
            'pending'  => 0,
            'failed'   => 0,
            'reserved' => 0,
            'oldest_pending' => null,
            'oldest_pending_age_seconds' => null,
        ];

        if ($driver === 'database') {
            if (Schema::hasTable('jobs')) {
                $base['pending']  = (int) DB::table('jobs')->count();
                $base['reserved'] = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
                $oldest = DB::table('jobs')->min('created_at');
                if ($oldest) {
                    $created = Carbon::parse(is_numeric($oldest) ? '@' . (int) $oldest : $oldest);
                    $base['oldest_pending'] = $created->toIso8601String();
                    $base['oldest_pending_age_seconds'] = $created->diffInSeconds(now());
                }
            }
            if (Schema::hasTable('failed_jobs')) {
                $base['failed'] = (int) DB::table('failed_jobs')->count();
            }
        }

        $base['health'] = $this->scoreHealth($base);
        return $base;
    }

    public function failedJobs(int $limit = 25): array
    {
        if (!Schema::hasTable('failed_jobs')) return [];

        return DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'connection', 'queue', 'failed_at'])
            ->map(fn($r) => [
                'id'         => $r->id,
                'connection' => $r->connection,
                'queue'      => $r->queue,
                'failed_at'  => $r->failed_at,
            ])
            ->all();
    }

    public function pendingJobs(int $limit = 25): array
    {
        if (!Schema::hasTable('jobs')) return [];

        return DB::table('jobs')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'queue', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->map(fn($r) => [
                'id'           => $r->id,
                'queue'        => $r->queue,
                'attempts'     => (int) $r->attempts,
                'reserved_at'  => $r->reserved_at ? Carbon::parse(is_numeric($r->reserved_at) ? '@' . (int) $r->reserved_at : $r->reserved_at)->toIso8601String() : null,
                'available_at' => Carbon::parse('@' . (int) $r->available_at)->toIso8601String(),
                'created_at'   => Carbon::parse('@' . (int) $r->created_at)->toIso8601String(),
            ])
            ->all();
    }

    private function scoreHealth(array $s): string
    {
        if ($s['failed'] > 50)                    return 'critical';
        if ($s['failed'] > 10)                    return 'warning';
        if (($s['oldest_pending_age_seconds'] ?? 0) > 600) return 'warning';
        return 'ok';
    }
}
