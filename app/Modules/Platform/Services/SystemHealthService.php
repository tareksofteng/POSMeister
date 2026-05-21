<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\SystemAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only health and operations endpoint backing /api/system/health.
 * Reports on critical infrastructure (DB, cache, queue, scheduler) plus
 * a set of opinionated readiness checks (writable storage paths,
 * accounting balanced, default admin password not still in use, etc.).
 */
class SystemHealthService
{
    public function snapshot(): array
    {
        return [
            'as_of'       => now()->toIso8601String(),
            'environment' => app()->environment(),
            'version'     => $this->version(),
            'php'         => PHP_VERSION,
            'laravel'     => app()->version(),
            'database'    => $this->checkDatabase(),
            'cache'       => $this->checkCache(),
            'queue'       => $this->checkQueue(),
            'storage'     => $this->checkStorage(),
            'modules'     => $this->moduleStatus(),
            'business'    => $this->businessChecks(),
            'recent_events' => $this->recentEvents(),
        ];
    }

    public function version(): array
    {
        return [
            'app'         => 'POSmeister',
            'version'     => 'v2.0.0',
            'phase'       => 'Phase X — Platform Hardening',
            'released_at' => '2026-05-24',
            'edition'     => env('POSMEISTER_EDITION', 'community'),
        ];
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('select 1');
            return [
                'ok'         => true,
                'driver'     => DB::connection()->getDriverName(),
                'name'       => DB::connection()->getDatabaseName(),
                'ping_ms'    => round((microtime(true) - $start) * 1000, 1),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        $key = 'health:probe:' . random_int(1000, 9999);
        try {
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);
            Cache::forget($key);
            return ['ok' => $value === 'ok', 'driver' => config('cache.default')];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => config('cache.default'), 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');
        $info = ['driver' => $driver, 'ok' => true];
        if ($driver === 'database' && Schema::hasTable('jobs')) {
            $info['pending']     = DB::table('jobs')->count();
            $info['failed']      = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $info['oldest_pending'] = DB::table('jobs')->min('created_at');
        }
        return $info;
    }

    private function checkStorage(): array
    {
        $paths = [
            'storage/app'             => storage_path('app'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs'            => storage_path('logs'),
            'bootstrap/cache'         => base_path('bootstrap/cache'),
        ];
        $rows = [];
        foreach ($paths as $label => $abs) {
            $rows[] = [
                'path'     => $label,
                'exists'   => is_dir($abs),
                'writable' => is_dir($abs) && is_writable($abs),
            ];
        }
        return $rows;
    }

    /**
     * Returns the list of detected modules (table-presence based) so the
     * dashboard can show which phases are migrated on this deployment.
     */
    private function moduleStatus(): array
    {
        $modules = [
            'sales'      => ['table' => 'sales',          'phase' => 'A'],
            'finance'    => ['table' => 'budgets',        'phase' => 'B'],
            'accounting' => ['table' => 'journal_entries','phase' => 'C'],
            'inventory'  => ['table' => 'inventory',      'phase' => 'D'],
            'crm'        => ['table' => 'customer_loyalty_profiles', 'phase' => 'E'],
            'oms'        => ['table' => 'orders',         'phase' => 'F'],
            'hrm'        => ['table' => 'employees',      'phase' => 'G'],
            'platform'   => ['table' => 'tenants',        'phase' => 'X'],
        ];
        $out = [];
        foreach ($modules as $key => $m) {
            $out[] = ['key' => $key, 'phase' => $m['phase'], 'migrated' => Schema::hasTable($m['table'])];
        }
        return $out;
    }

    /**
     * Opinionated business-state checks: payroll backlog, accounting
     * balance, demo passwords, etc.
     */
    private function businessChecks(): array
    {
        $checks = [];

        // Default admin password still in use → warn.
        if (Schema::hasTable('users')) {
            $defaultAdmin = DB::table('users')->where('email', 'admin@posmeister.local')->first();
            $checks[] = [
                'key'      => 'default_admin_password',
                'severity' => $defaultAdmin ? 'warning' : 'info',
                'message'  => $defaultAdmin
                    ? 'Default admin@posmeister.local account exists. Change password or remove for production.'
                    : 'No default admin account detected.',
                'ok'       => !$defaultAdmin,
            ];
        }

        // Accounting balance check (sum debit must equal sum credit).
        if (Schema::hasTable('journal_entry_lines')) {
            $row = DB::table('journal_entry_lines')
                ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
                ->first();
            $delta = abs((float) $row->d - (float) $row->c);
            $checks[] = [
                'key'      => 'ledger_balanced',
                'severity' => $delta < 0.01 ? 'info' : 'critical',
                'message'  => $delta < 0.01
                    ? 'Journal ledger is balanced.'
                    : "Journal ledger imbalanced by {$delta}.",
                'ok'       => $delta < 0.01,
            ];
        }

        // Pending payroll approvals.
        if (Schema::hasTable('payslips') && Schema::hasColumn('payslips', 'approval_status')) {
            $pending = DB::table('payslips')->where('approval_status', 'submitted')->count();
            if ($pending > 0) {
                $checks[] = [
                    'key'      => 'pending_payroll',
                    'severity' => 'info',
                    'message'  => "{$pending} payslips awaiting approval.",
                    'ok'       => true,
                ];
            }
        }

        // Failed background jobs.
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $checks[] = [
                    'key'      => 'failed_jobs',
                    'severity' => 'warning',
                    'message'  => "{$failed} failed background jobs in queue.",
                    'ok'       => false,
                ];
            }
        }

        // Application key present.
        $checks[] = [
            'key'      => 'app_key',
            'severity' => env('APP_KEY') ? 'info' : 'critical',
            'message'  => env('APP_KEY') ? 'APP_KEY is set.' : 'APP_KEY missing in .env.',
            'ok'       => (bool) env('APP_KEY'),
        ];

        // Debug mode in production.
        if (app()->environment('production') && config('app.debug')) {
            $checks[] = [
                'key'      => 'debug_in_production',
                'severity' => 'critical',
                'message'  => 'APP_DEBUG is enabled in production — disable immediately.',
                'ok'       => false,
            ];
        }

        return $checks;
    }

    private function recentEvents(int $limit = 10): array
    {
        if (!Schema::hasTable('system_audit_logs')) return [];

        return SystemAuditLog::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'action', 'severity', 'note', 'actor_id', 'created_at'])
            ->map(fn($r) => [
                'id'        => $r->id,
                'action'    => $r->action,
                'severity'  => $r->severity,
                'note'      => $r->note,
                'actor_id'  => $r->actor_id,
                'created_at'=> $r->created_at,
            ])->all();
    }
}
