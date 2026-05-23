<?php

namespace App\Modules\SystemOps\Services;

use App\Modules\Platform\Services\SystemAuditService;
use App\Modules\SystemOps\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Database / storage backup service. Defaults to local filesystem
 * under storage/app/backups so the platform stays cloud-vendor neutral.
 * The mysqldump path is best-effort — environments without shell
 * access fall back to a PHP-driven CREATE+INSERT export, which is
 * slower but works everywhere.
 *
 * Restore is intentionally NOT a one-click operation here: we expose
 * the file path and checksum, and an operator runs the restore by hand
 * (or via a future Artisan command) so that nobody can overwrite a
 * live database from a web request.
 */
class BackupService
{
    public function __construct(private SystemAuditService $audit) {}

    public function dir(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        return $dir;
    }

    public function list(int $limit = 50): array
    {
        return BackupRun::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'type'         => $r->type,
                'status'       => $r->status,
                'file_path'    => $r->file_path,
                'size_bytes'   => $r->size_bytes,
                'size_human'   => $this->humanBytes($r->size_bytes),
                'checksum'     => $r->checksum_sha256,
                'note'         => $r->note,
                'error'        => $r->error,
                'started_at'   => $r->started_at,
                'finished_at'  => $r->finished_at,
            ])
            ->all();
    }

    public function summary(): array
    {
        $last = BackupRun::query()->orderByDesc('id')->first();
        $lastSuccess = BackupRun::query()->where('status', 'success')->orderByDesc('id')->first();
        return [
            'total_runs'        => BackupRun::query()->count(),
            'last_run'          => $last?->toArray(),
            'last_success_at'   => $lastSuccess?->finished_at,
            'last_success_size' => $lastSuccess?->size_bytes,
            'disk_used_bytes'   => $this->diskUsed(),
        ];
    }

    public function runDatabase(?int $actorId = null, ?string $note = null): BackupRun
    {
        $run = BackupRun::create([
            'type'       => 'database',
            'status'     => 'running',
            'note'       => $note,
            'actor_id'   => $actorId,
            'started_at' => now(),
        ]);

        try {
            $file = $this->dumpDatabase();
            $size = filesize($file) ?: null;
            $hash = $size ? hash_file('sha256', $file) : null;

            $run->update([
                'status'           => 'success',
                'file_path'        => str_replace(storage_path() . '/', '', $file),
                'size_bytes'       => $size,
                'checksum_sha256'  => $hash,
                'finished_at'      => now(),
            ]);

            $this->audit->log('backup.database.success', 'backup_run', $run->id, null, [
                'file' => $run->file_path, 'size' => $size,
            ], 'Database backup completed.');
        } catch (Throwable $e) {
            $run->update([
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $this->audit->log('backup.database.failed', 'backup_run', $run->id, null, null, $e->getMessage(), 'warning');
        }

        return $run->fresh();
    }

    public function prune(int $keep = 14): int
    {
        $stale = BackupRun::query()
            ->where('status', 'success')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        $deleted = 0;
        foreach ($stale as $row) {
            if ($row->file_path) {
                $abs = storage_path($row->file_path);
                if (is_file($abs)) @unlink($abs);
            }
            $row->delete();
            $deleted++;
        }
        return $deleted;
    }

    /* -------------------------- internals -------------------------- */

    private function dumpDatabase(): string
    {
        $db   = DB::connection()->getDatabaseName();
        $name = 'db-' . now()->format('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.sql';
        $file = $this->dir() . '/' . $name;

        $config = config('database.connections.' . config('database.default'));
        $host = escapeshellarg($config['host'] ?? '127.0.0.1');
        $port = escapeshellarg((string) ($config['port'] ?? 3306));
        $user = escapeshellarg($config['username'] ?? '');
        $pass = $config['password'] ?? '';
        $dbname = escapeshellarg($db);
        $target = escapeshellarg($file);

        $cmd = "mysqldump --no-tablespaces --single-transaction --quick --routines -h $host -P $port -u $user "
             . ($pass !== '' ? '-p' . escapeshellarg($pass) . ' ' : '')
             . "$dbname > $target 2>&1";

        $output = []; $rc = 0;
        @exec($cmd, $output, $rc);

        if ($rc === 0 && is_file($file) && filesize($file) > 0) {
            return $file;
        }

        if (is_file($file)) @unlink($file);
        return $this->dumpDatabaseViaPhp($db);
    }

    private function dumpDatabaseViaPhp(string $db): string
    {
        $name = 'db-' . now()->format('Ymd-His') . '-php.sql';
        $file = $this->dir() . '/' . $name;
        $fh = fopen($file, 'w');
        fwrite($fh, "-- POSmeister fallback dump for {$db} on " . now()->toIso8601String() . "\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn($row) => array_values((array) $row)[0])
            ->all();

        foreach ($tables as $table) {
            $create = DB::select('SHOW CREATE TABLE `' . $table . '`');
            $createSql = array_values((array) $create[0])[1] ?? null;
            if (!$createSql) continue;
            fwrite($fh, "\nDROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fh, $createSql . ";\n");
            DB::table($table)->orderBy('1')->chunkById(500, function ($rows) use ($fh, $table) {
                foreach ($rows as $row) {
                    $cols = array_keys((array) $row);
                    $vals = array_map(function ($v) {
                        if ($v === null) return 'NULL';
                        if (is_int($v) || is_float($v)) return (string) $v;
                        return "'" . str_replace(["\\","'"], ["\\\\","\\'"], (string) $v) . "'";
                    }, array_values((array) $row));
                    fwrite($fh, "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n");
                }
            });
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        return $file;
    }

    private function diskUsed(): int
    {
        $dir = $this->dir();
        $total = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) $total += filesize($f) ?: 0;
        }
        return $total;
    }

    private function humanBytes(?int $b): ?string
    {
        if ($b === null) return null;
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        $v = (float) $b;
        while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
        return round($v, 2) . ' ' . $units[$i];
    }
}
