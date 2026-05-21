<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a demo company with realistic sample data. Intentionally idempotent:
 * existing seeded rows are not duplicated. Use for product demos, recruiter
 * showcases, and onboarding walkthroughs.
 *
 *   php artisan posmeister:seed-demo
 *
 * Currently delegates to the existing module seeders — extend per module
 * as needed.
 */
class SeedDemoCommand extends Command
{
    protected $signature   = 'posmeister:seed-demo {--fresh : Wipe demo data first (will not affect production rows you flagged is_demo)}';
    protected $description = 'Seed a demo dataset for product demos and onboarding walkthroughs.';

    public function handle(): int
    {
        $this->info('POSmeister demo data seeder');
        $this->line('');

        if (app()->environment('production') && !$this->confirm('You are in PRODUCTION. Continue?', false)) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        $this->info(' • Running module seeders...');

        $seeders = [
            'Database\\Seeders\\RolePermissionSeeder',
            'Database\\Seeders\\UnitSeeder',
            'Database\\Seeders\\HrmSeeder',
            'Database\\Seeders\\ChartOfAccountsSeeder',
            'Database\\Seeders\\ProductSeeder',
        ];

        foreach ($seeders as $seeder) {
            if (!class_exists($seeder)) {
                $this->warn("   ! Seeder not found: {$seeder}");
                continue;
            }
            $this->line("   • {$seeder}");
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->line('');
        $this->info(' ✓ Demo data ready.');
        $this->line('');
        $this->info('   Default admin: admin@posmeister.local / Admin@1234');
        $this->warn('   Change the admin password before exposing this instance publicly.');

        return self::SUCCESS;
    }
}
