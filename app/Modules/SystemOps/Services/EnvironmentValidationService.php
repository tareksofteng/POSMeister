<?php

namespace App\Modules\SystemOps\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Deep environment validation. Where SystemHealthService gives a fast
 * status pulse for dashboards, this service runs the kind of checks
 * you actually want before flipping a production deploy live: PHP
 * version + extensions, database connectivity, cache write-through,
 * mail driver configuration, storage permissions, queue driver,
 * scheduler last-run, APP_KEY presence, debug-in-prod, etc.
 */
class EnvironmentValidationService
{
    public function run(): array
    {
        return [
            'as_of'      => now()->toIso8601String(),
            'php'        => $this->checkPhp(),
            'extensions' => $this->checkExtensions(),
            'database'   => $this->checkDatabase(),
            'cache'      => $this->checkCache(),
            'queue'      => $this->checkQueue(),
            'mail'       => $this->checkMail(),
            'storage'    => $this->checkStorage(),
            'security'   => $this->checkSecurity(),
            'scheduler'  => $this->checkScheduler(),
        ];
    }

    private function checkPhp(): array
    {
        return [
            'version'    => PHP_VERSION,
            'sapi'       => PHP_SAPI,
            'memory'     => ini_get('memory_limit'),
            'time_zone'  => date_default_timezone_get(),
            'ok'         => version_compare(PHP_VERSION, '8.2.0', '>='),
            'message'    => version_compare(PHP_VERSION, '8.2.0', '>=')
                ? 'PHP version meets minimum (8.2+).'
                : 'PHP version below 8.2 — Laravel 13 requires 8.2 or higher.',
        ];
    }

    private function checkExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'curl', 'fileinfo', 'bcmath', 'ctype', 'xml'];
        $rows = [];
        foreach ($required as $ext) {
            $rows[] = ['name' => $ext, 'loaded' => extension_loaded($ext)];
        }
        $missing = array_filter($rows, fn($r) => !$r['loaded']);
        return [
            'required' => $rows,
            'ok'       => empty($missing),
            'message'  => empty($missing) ? 'All required PHP extensions are loaded.' : 'Missing extensions: ' . implode(', ', array_column($missing, 'name')),
        ];
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            return [
                'ok'      => true,
                'driver'  => DB::connection()->getDriverName(),
                'name'    => DB::connection()->getDatabaseName(),
                'ping_ms' => round((microtime(true) - $start) * 1000, 1),
                'message' => 'Database is reachable.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Database unreachable: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        $key = 'sysops:probe:' . bin2hex(random_bytes(4));
        try {
            Cache::put($key, 'ok', 5);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);
            return ['ok' => $ok, 'driver' => config('cache.default'), 'message' => $ok ? 'Cache write/read succeeded.' : 'Cache returned unexpected value.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => config('cache.default'), 'message' => 'Cache failure: ' . $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');
        $known = ['sync', 'database', 'redis', 'beanstalkd', 'sqs'];
        return [
            'driver'  => $driver,
            'ok'      => in_array($driver, $known, true),
            'message' => $driver === 'sync'
                ? 'Queue driver is sync — fine for dev, switch to redis/database in production.'
                : "Queue driver is {$driver}.",
        ];
    }

    private function checkMail(): array
    {
        $driver = config('mail.default');
        $from   = config('mail.from.address');
        $ok     = !empty($from) && $from !== 'hello@example.com';
        return [
            'driver'  => $driver,
            'from'    => $from,
            'ok'      => $ok,
            'message' => $ok ? "Mail configured with {$driver}." : 'Mail from-address is missing or still the default.',
        ];
    }

    private function checkStorage(): array
    {
        $paths = [
            'storage/app'             => storage_path('app'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs'            => storage_path('logs'),
            'bootstrap/cache'         => base_path('bootstrap/cache'),
            'storage/app/backups'     => storage_path('app/backups'),
        ];
        $rows = [];
        foreach ($paths as $label => $abs) {
            $rows[] = [
                'path'     => $label,
                'exists'   => is_dir($abs),
                'writable' => is_dir($abs) && is_writable($abs),
            ];
        }
        $bad = array_filter($rows, fn($r) => !$r['writable']);
        return ['paths' => $rows, 'ok' => empty($bad), 'message' => empty($bad) ? 'All storage paths writable.' : count($bad) . ' path(s) not writable.'];
    }

    private function checkSecurity(): array
    {
        $issues = [];
        if (!env('APP_KEY')) {
            $issues[] = 'APP_KEY is missing.';
        }
        if (app()->environment('production') && config('app.debug')) {
            $issues[] = 'APP_DEBUG is enabled in production.';
        }
        if (env('APP_URL') === 'http://localhost') {
            $issues[] = 'APP_URL is still http://localhost.';
        }
        return ['ok' => empty($issues), 'issues' => $issues, 'message' => empty($issues) ? 'Security configuration is sane.' : implode(' ', $issues)];
    }

    private function checkScheduler(): array
    {
        $last = Cache::get('sysops:scheduler:last_ping');
        $age  = $last ? now()->diffInSeconds($last) : null;
        $ok   = $age !== null && $age < 120;
        return [
            'last_ping'    => $last,
            'age_seconds'  => $age,
            'ok'           => $ok,
            'message'      => $ok
                ? 'Scheduler heartbeat received within the last 2 minutes.'
                : ($last ? "Scheduler heartbeat is {$age}s old — cron may have stopped." : 'No scheduler heartbeat recorded — cron is likely not configured.'),
        ];
    }
}
