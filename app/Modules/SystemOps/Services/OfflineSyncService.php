<?php

namespace App\Modules\SystemOps\Services;

use App\Modules\SystemOps\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline sync helper. Two responsibilities:
 *
 *   1. begin(key) / commit(key, ...) lets a write endpoint reserve an
 *      Idempotency-Key, do its work, and record the resulting entity
 *      so a retried request finds the same row instead of creating
 *      a duplicate.
 *
 *   2. dashboard helpers (pendingCount, recentSynced) for the
 *      SyncRecoveryView so admins can see what came in offline.
 */
class OfflineSyncService
{
    public function readKey(Request $req): ?string
    {
        $key = $req->header('Idempotency-Key') ?: $req->input('idempotency_key');
        if (!$key) return null;
        $key = substr(preg_replace('/[^A-Za-z0-9_\-:.]/', '', $key), 0, 80);
        return $key !== '' ? $key : null;
    }

    public function existing(?string $key): ?IdempotencyKey
    {
        if (!$key) return null;
        return IdempotencyKey::query()->where('key', $key)->first();
    }

    public function record(string $key, string $entityType, int $entityId, int $status, ?int $actorId, ?string $ip, $payload = null): IdempotencyKey
    {
        return IdempotencyKey::updateOrCreate(
            ['key' => $key],
            [
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'response_status' => $status,
                'response_hash'   => $payload !== null ? hash('sha256', json_encode($payload)) : null,
                'actor_id'        => $actorId,
                'actor_ip'        => $ip,
                'created_at'      => now(),
            ]
        );
    }

    public function summary(): array
    {
        $hasSales = Schema::hasTable('sales') && Schema::hasColumn('sales', 'idempotency_key');
        $offlineSales = $hasSales ? DB::table('sales')->whereNotNull('idempotency_key')->count() : 0;
        $syncedToday = $hasSales ? DB::table('sales')->whereNotNull('offline_synced_at')->whereDate('offline_synced_at', today())->count() : 0;
        $total = IdempotencyKey::query()->count();
        $last24h = IdempotencyKey::query()->where('created_at', '>=', now()->subDay())->count();

        return [
            'idempotency_keys_total' => $total,
            'idempotency_keys_24h'   => $last24h,
            'offline_sales_total'    => $offlineSales,
            'offline_sales_today'    => $syncedToday,
        ];
    }

    public function recent(int $limit = 30): array
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'idempotency_key')) {
            return [];
        }
        return DB::table('sales')
            ->whereNotNull('idempotency_key')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'sale_number', 'idempotency_key', 'offline_reference', 'offline_synced_at', 'created_at', 'grand_total'])
            ->map(fn($r) => [
                'id'                 => $r->id,
                'sale_number'        => $r->sale_number,
                'idempotency_key'    => $r->idempotency_key,
                'offline_reference'  => $r->offline_reference,
                'offline_synced_at'  => $r->offline_synced_at,
                'created_at'         => $r->created_at,
                'grand_total'        => $r->grand_total,
            ])
            ->all();
    }

    public function prune(int $keepHours = 168): int
    {
        return IdempotencyKey::query()
            ->where('created_at', '<', now()->subHours($keepHours))
            ->delete();
    }
}
