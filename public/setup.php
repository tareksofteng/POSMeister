<?php
/*
 |--------------------------------------------------------------------------
 | POSmeister one-shot post-deploy setup
 |--------------------------------------------------------------------------
 |
 | After extracting the zip on a fresh cPanel host, open this URL once:
 |
 |   https://posmaster.tareksofteng.com/setup.php
 |
 | It performs all the steps that normally need shell access:
 |
 |   1. Re-creates the public/storage symlink (storage:link)
 |   2. Fixes permissions on storage/ + bootstrap/cache/ so Laravel can write
 |   3. Re-creates any missing framework cache subfolders
 |   4. Clears compiled views / config / route caches
 |   5. Self-deletes so the URL is not exposed forever
 |
 | Safe to re-run (idempotent) — every action is best-effort. The page
 | prints a green/red status table so you can see what worked.
 */

// ── Hard timeout / memory bumps for slow shared hosts ─────────────────────
@set_time_limit(60);
@ini_set('memory_limit', '256M');

header('Content-Type: text/html; charset=utf-8');

$projectRoot = dirname(__DIR__);   // .../posmaster.tareksofteng.com
$publicDir   = __DIR__;            // .../posmaster.tareksofteng.com/public
$storageReal = $projectRoot.'/storage/app/public';
$storageLink = $publicDir.'/storage';

$results = [];
function step(string $label, $ok, string $detail = ''): void {
    global $results;
    $results[] = [$label, (bool) $ok, $detail];
}

// ── 1) Re-create public/storage symlink ───────────────────────────────────
try {
    if (is_link($storageLink) || file_exists($storageLink)) {
        @unlink($storageLink) || @rmdir($storageLink);
    }
    if (!is_dir($storageReal)) {
        @mkdir($storageReal, 0775, true);
    }
    $ok = @symlink($storageReal, $storageLink);
    step('public/storage symlink', $ok, $ok ? $storageReal : 'symlink() returned false — host may block symlinks');
} catch (Throwable $e) {
    step('public/storage symlink', false, $e->getMessage());
}

// ── 2) Permissions on storage/ + bootstrap/cache ──────────────────────────
function chmodRecursive(string $path, int $dirMode, int $fileMode): int {
    if (!is_dir($path)) return 0;
    $touched = 0;
    @chmod($path, $dirMode); $touched++;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        @chmod($item->getPathname(), $item->isDir() ? $dirMode : $fileMode);
        $touched++;
    }
    return $touched;
}

$touched1 = chmodRecursive($projectRoot.'/storage', 0775, 0664);
step('chmod storage/ (775/664)', $touched1 > 0, $touched1.' entries touched');

$touched2 = chmodRecursive($projectRoot.'/bootstrap/cache', 0775, 0664);
step('chmod bootstrap/cache/ (775/664)', $touched2 > 0, $touched2.' entries touched');

// ── 3) Ensure framework cache subfolders exist ────────────────────────────
$frameworkDirs = [
    $projectRoot.'/storage/framework/cache/data',
    $projectRoot.'/storage/framework/sessions',
    $projectRoot.'/storage/framework/views',
    $projectRoot.'/storage/logs',
    $projectRoot.'/storage/app/public/settings',
    $projectRoot.'/storage/app/public/employees',
    $projectRoot.'/storage/app/public/products',
];
$missing = [];
foreach ($frameworkDirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
        if (!is_dir($d)) $missing[] = $d;
    }
}
step('storage subfolders present', empty($missing), $missing ? implode(', ', $missing) : 'all present');

// ── 4) Clear Laravel caches via artisan ───────────────────────────────────
function tryArtisan(string $cmd, string $projectRoot): array {
    // Look for a php binary in the usual cPanel locations
    $bins = ['/usr/local/bin/php', '/usr/bin/php', PHP_BINARY, 'php'];
    foreach ($bins as $bin) {
        if (!$bin) continue;
        $full = escapeshellcmd($bin).' '.escapeshellarg($projectRoot.'/artisan').' '.$cmd.' 2>&1';
        $out = @shell_exec($full);
        if ($out !== null && $out !== false) return [true, trim($out)];
    }
    return [false, 'shell_exec disabled or php binary not found'];
}

foreach (['view:clear', 'config:clear', 'cache:clear', 'route:clear'] as $cmd) {
    [$ok, $detail] = tryArtisan($cmd, $projectRoot);
    step("artisan {$cmd}", $ok, $detail);
}

// ── 5) Self-delete (best effort) ──────────────────────────────────────────
$selfDeleted = @unlink(__FILE__);

// ── Render report ─────────────────────────────────────────────────────────
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>POSmeister — Post-Deploy Setup</title>
<style>
    body  { font: 14px/1.5 -apple-system, Segoe UI, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 16px; color: #1f2937; background:#f8fafc; }
    h1    { font-size: 22px; margin: 0 0 4px; }
    p.sub { color: #64748b; margin: 0 0 24px; }
    table { width: 100%; border-collapse: collapse; background:#fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    th    { background: #f8fafc; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    tr:last-child td { border-bottom: none; }
    .ok   { color: #047857; font-weight: 600; }
    .bad  { color: #b91c1c; font-weight: 600; }
    .detail { color: #64748b; font-family: ui-monospace, monospace; font-size: 12px; }
    .footer { margin-top: 24px; padding: 14px 16px; border-radius: 10px; }
    .footer.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .footer.bad{ background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
</head>
<body>
    <h1>POSmeister — Post-Deploy Setup</h1>
    <p class="sub">One-time housekeeping after extracting the deploy zip.</p>

    <table>
        <thead><tr><th>Step</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($results as [$label, $ok, $detail]): ?>
            <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓ OK' : '✗ Failed' ?></td>
                <td class="detail"><?= htmlspecialchars($detail) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer <?= $selfDeleted ? 'ok' : 'bad' ?>">
        <?php if ($selfDeleted): ?>
            ✓ setup.php has deleted itself. You can now go to
            <a href="/"><strong>posmaster.tareksofteng.com</strong></a> and log in.
        <?php else: ?>
            ⚠ Could not auto-delete setup.php. <strong>Delete it manually via File Manager</strong>
            (public/setup.php) so it isn't exposed publicly.
        <?php endif; ?>
    </div>
</body>
</html>
