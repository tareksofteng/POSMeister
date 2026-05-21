<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-flight production readiness check. Run before going live:
 *   php artisan posmeister:check-env
 *
 * Exits with code 0 only if every required check passes.
 */
class CheckEnvCommand extends Command
{
    protected $signature   = 'posmeister:check-env {--strict : Fail on warnings as well as errors}';
    protected $description = 'Run a production readiness checklist for this POSmeister deployment.';

    public function handle(): int
    {
        $this->info('POSmeister production-readiness check');
        $this->line('');

        $errors   = 0;
        $warnings = 0;

        // --- Critical ----------------------------------------------------
        $errors += $this->checkRequired('APP_KEY',     'APP_KEY must be set.');
        $errors += $this->checkRequired('APP_URL',     'APP_URL must be set.');
        $errors += $this->checkRequired('DB_DATABASE', 'DB_DATABASE must be set.');

        if (app()->environment('production') && config('app.debug')) {
            $this->error(' ✗ APP_DEBUG=true in production — disable immediately.');
            $errors++;
        } else {
            $this->info(' ✓ APP_DEBUG correctly configured for ' . app()->environment());
        }

        // --- Database reachability ---------------------------------------
        try {
            DB::select('select 1');
            $this->info(' ✓ Database reachable: ' . DB::connection()->getDatabaseName());
        } catch (\Throwable $e) {
            $this->error(' ✗ Database not reachable: ' . $e->getMessage());
            $errors++;
        }

        // --- Storage writability -----------------------------------------
        foreach (['storage/framework/cache', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $rel) {
            $abs = base_path($rel);
            if (!is_dir($abs)) {
                $this->warn(' ! Missing directory: ' . $rel);
                $warnings++;
            } elseif (!is_writable($abs)) {
                $this->error(' ✗ Not writable: ' . $rel);
                $errors++;
            } else {
                $this->info(' ✓ Writable: ' . $rel);
            }
        }

        // --- Warnings ----------------------------------------------------
        if (config('app.url') === 'http://localhost') {
            $this->warn(' ! APP_URL is still http://localhost — set it to the public domain.');
            $warnings++;
        }
        if (env('MAIL_FROM_ADDRESS', 'hello@example.com') === 'hello@example.com') {
            $this->warn(' ! MAIL_FROM_ADDRESS is the default placeholder.');
            $warnings++;
        }
        if (DB::table('users')->where('email', 'admin@posmeister.local')->exists()) {
            $this->warn(' ! Default admin account (admin@posmeister.local) still present — change or remove for production.');
            $warnings++;
        }

        $this->line('');
        $this->info("Summary: {$errors} errors, {$warnings} warnings.");

        if ($errors > 0) return self::FAILURE;
        if ($warnings > 0 && $this->option('strict')) return self::FAILURE;
        return self::SUCCESS;
    }

    private function checkRequired(string $key, string $message): int
    {
        if (env($key)) {
            $this->info(" ✓ {$key} is set.");
            return 0;
        }
        $this->error(' ✗ ' . $message);
        return 1;
    }
}
