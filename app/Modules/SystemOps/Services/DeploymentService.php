<?php

namespace App\Modules\SystemOps\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deployment / version info. Reads a tagged build version from a file
 * dropped during CI (storage/app/.build.json), falls back to env, and
 * surfaces migration state so an operator can see how the current
 * deployment compares to the codebase's expected schema.
 */
class DeploymentService
{
    public function info(): array
    {
        return [
            'app'           => 'POSmeister',
            'version'       => $this->buildVersion(),
            'release'       => $this->releaseTag(),
            'commit'        => $this->commit(),
            'built_at'      => $this->builtAt(),
            'environment'   => app()->environment(),
            'php'           => PHP_VERSION,
            'laravel'       => app()->version(),
            'maintenance'   => app()->isDownForMaintenance(),
            'migrations'    => $this->migrationState(),
            'release_notes' => $this->releaseNotes(),
        ];
    }

    public function buildVersion(): string
    {
        $build = $this->readBuildFile();
        return $build['version'] ?? env('APP_VERSION', 'v2.0.0');
    }

    private function releaseTag(): string
    {
        $build = $this->readBuildFile();
        return $build['release'] ?? 'Phase Z — Production & PWA';
    }

    private function commit(): ?string
    {
        $build = $this->readBuildFile();
        if (!empty($build['commit'])) return $build['commit'];
        $head = base_path('.git/HEAD');
        if (is_readable($head)) {
            $ref = trim(@file_get_contents($head) ?: '');
            if (str_starts_with($ref, 'ref: ')) {
                $sha = @file_get_contents(base_path('.git/' . substr($ref, 5)));
                return $sha ? substr(trim($sha), 0, 12) : null;
            }
            return substr($ref, 0, 12);
        }
        return null;
    }

    private function builtAt(): ?string
    {
        $build = $this->readBuildFile();
        if (!empty($build['built_at'])) return $build['built_at'];
        $manifest = public_path('build/manifest.json');
        return is_file($manifest) ? date('c', filemtime($manifest)) : null;
    }

    private function migrationState(): array
    {
        if (!Schema::hasTable('migrations')) {
            return ['ok' => false, 'message' => 'migrations table missing'];
        }
        $applied = (int) DB::table('migrations')->count();
        $files = glob(database_path('migrations/*.php')) ?: [];
        $expected = count($files);
        return [
            'applied'  => $applied,
            'expected' => $expected,
            'pending'  => max($expected - $applied, 0),
            'ok'       => $applied >= $expected,
        ];
    }

    private function readBuildFile(): array
    {
        $path = storage_path('app/.build.json');
        if (!is_file($path)) return [];
        $raw = @file_get_contents($path);
        $decoded = $raw ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private function releaseNotes(): array
    {
        $build = $this->readBuildFile();
        if (!empty($build['notes']) && is_array($build['notes'])) {
            return $build['notes'];
        }
        return [
            'Progressive Web App support with installable shell',
            'Offline-capable POS with idempotent re-sync',
            'Backup & restore foundation (local-first)',
            'Environment validation and operations diagnostics',
            'Docker production stack',
        ];
    }
}
