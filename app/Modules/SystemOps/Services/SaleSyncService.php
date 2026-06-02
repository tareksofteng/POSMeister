<?php

namespace App\Modules\SystemOps\Services;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleService;
use App\Modules\SystemOps\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Atomic batch importer for offline-originated sales.
 *
 *   - Every payload is required to ship an idempotency_key. If the key
 *     has been seen before (or the resulting sale row already carries it),
 *     the row is skipped with status=duplicate and we return the original
 *     server id so the client can map its temporary invoice.
 *
 *   - Each individual sale runs through SaleService::store() so all
 *     downstream observers (accounting auto-post, stock movement, loyalty
 *     accrual, etc.) fire exactly the same way as for online sales.
 *
 *   - Failures are recorded per-row in sync_conflicts so an admin can
 *     review without halting the rest of the batch.
 */
class SaleSyncService
{
    public function __construct(private SaleService $sales) {}

    /**
     * @param string $deviceId   Client-generated device fingerprint
     * @param ?int   $userId     Acting user (sync request must be authenticated)
     * @param array  $rows       [['idempotency_key', 'offline_reference', 'data']]
     * @return array             ['batch_id' => int, 'results' => [...]]
     */
    public function importBatch(string $deviceId, ?int $userId, array $rows): array
    {
        $this->touchDevice($deviceId, $userId);

        $batchId = $this->openBatch($deviceId, $userId, count($rows));
        $results = [];
        $ok = $dup = $fail = 0;

        foreach ($rows as $row) {
            $key = $row['idempotency_key'] ?? null;
            $ref = $row['offline_reference'] ?? null;
            $data = $row['data'] ?? [];

            if (!$key) {
                $results[] = ['idempotency_key' => $key, 'status' => 'failed', 'error' => 'missing idempotency_key'];
                $this->logConflict($batchId, $deviceId, 'sale', $key, 'validation', 'missing idempotency_key', $row);
                $fail++;
                continue;
            }

            $existing = $this->resolveExisting($key);
            if ($existing) {
                $results[] = ['idempotency_key' => $key, 'status' => 'duplicate', 'sale' => $existing];
                $dup++;
                continue;
            }

            try {
                $sale = DB::transaction(function () use ($data, $key, $ref, $userId) {
                    $data['idempotency_key']   = $key;
                    $data['offline_reference'] = $ref;
                    if (!isset($data['sale_date'])) $data['sale_date'] = now()->toDateString();
                    // Offline sale: the cashier already handed the goods over,
                    // so the server replays without re-validating stock.
                    $data['_skip_stock_check'] = true;

                    $sale = $this->sales->store($data);
                    if (Schema::hasColumn('sales', 'idempotency_key')) {
                        $sale->idempotency_key   = $key;
                        $sale->offline_reference = $ref;
                        $sale->offline_synced_at = now();
                        $sale->save();
                    }
                    IdempotencyKey::updateOrCreate(
                        ['key' => $key],
                        ['entity_type' => 'sale', 'entity_id' => $sale->id, 'response_status' => 201, 'actor_id' => $userId, 'created_at' => now()]
                    );
                    return $sale;
                });

                $results[] = ['idempotency_key' => $key, 'status' => 'ok', 'sale' => $this->shape($sale)];
                $ok++;
            } catch (Throwable $e) {
                $reason = $this->classifyError($e);
                $results[] = ['idempotency_key' => $key, 'status' => 'failed', 'error' => $e->getMessage(), 'reason' => $reason];
                $this->logConflict($batchId, $deviceId, 'sale', $key, $reason, $e->getMessage(), $row);
                $fail++;
            }
        }

        $this->closeBatch($batchId, $ok, $dup, $fail);

        return ['batch_id' => $batchId, 'results' => $results, 'summary' => compact('ok', 'dup', 'fail')];
    }

    /* -------------------------- internals -------------------------- */

    private function resolveExisting(string $key): ?array
    {
        // Hit the dedupe table first (cheapest path).
        $row = IdempotencyKey::query()->where('key', $key)->first();
        if ($row && $row->entity_id) {
            $sale = Sale::query()->find($row->entity_id);
            if ($sale) return $this->shape($sale);
        }
        // Belt-and-braces: also check the sales row directly.
        if (Schema::hasColumn('sales', 'idempotency_key')) {
            $sale = Sale::query()->where('idempotency_key', $key)->first();
            if ($sale) return $this->shape($sale);
        }
        return null;
    }

    private function shape(Sale $sale): array
    {
        return ['id' => $sale->id, 'sale_number' => $sale->sale_number];
    }

    private function classifyError(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'bestand') || str_contains($msg, 'stock')) return 'stock';
        if (str_contains($msg, 'validation')) return 'validation';
        return 'other';
    }

    private function touchDevice(string $deviceId, ?int $userId): void
    {
        if (!Schema::hasTable('device_sessions')) return;
        $request = request();
        DB::table('device_sessions')->updateOrInsert(
            ['device_id' => $deviceId],
            [
                'user_id'       => $userId,
                'user_agent'    => substr((string) $request->userAgent(), 0, 255),
                'last_ip'       => $request->ip(),
                'last_seen_at'  => now(),
                'updated_at'    => now(),
                'created_at'    => DB::raw('COALESCE(created_at, NOW())'),
                'first_seen_at' => DB::raw('COALESCE(first_seen_at, NOW())'),
            ]
        );
    }

    private function openBatch(string $deviceId, ?int $userId, int $total): ?int
    {
        if (!Schema::hasTable('sync_batches')) return null;
        return DB::table('sync_batches')->insertGetId([
            'device_id'   => $deviceId,
            'user_id'     => $userId,
            'status'      => 'running',
            'total_count' => $total,
            'started_at'  => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function closeBatch(?int $batchId, int $ok, int $dup, int $fail): void
    {
        if (!$batchId || !Schema::hasTable('sync_batches')) return;
        $status = $fail === 0 ? 'succeeded' : ($ok + $dup > 0 ? 'partial' : 'failed');
        DB::table('sync_batches')->where('id', $batchId)->update([
            'ok_count'        => $ok,
            'duplicate_count' => $dup,
            'failed_count'    => $fail,
            'status'          => $status,
            'finished_at'     => now(),
            'updated_at'      => now(),
        ]);
    }

    private function logConflict(?int $batchId, string $deviceId, string $entity, ?string $key, string $reason, string $message, array $payload): void
    {
        if (!Schema::hasTable('sync_conflicts')) return;
        DB::table('sync_conflicts')->insert([
            'batch_id'        => $batchId,
            'device_id'       => $deviceId,
            'entity'          => $entity,
            'idempotency_key' => $key,
            'reason'          => $reason,
            'message'         => $message,
            'payload'         => json_encode($payload),
            'resolution'      => 'open',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
