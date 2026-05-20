<?php

namespace App\Modules\OMS\Services;

use App\Modules\OMS\Ecommerce\EcommerceAdapterRegistry;
use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

/**
 * Orchestrates pull / push syncs against any registered e-commerce
 * connector. Heavy lifting lives in the adapter; this service just
 * manages the SyncJob state machine and error reporting.
 *
 * In production a Laravel queue job would call run() asynchronously.
 * For now it executes inline so the UI flow can be exercised.
 */
class EcommerceSyncService
{
    public function __construct(private readonly EcommerceAdapterRegistry $adapters) {}

    public function queue(EcommerceConnector $connector, string $entity, string $direction = 'pull'): SyncJob
    {
        if (!$connector->is_active) {
            throw new RuntimeException('Connector is not active.');
        }
        if (!in_array($entity, ['products', 'stock', 'customers', 'orders'], true)) {
            throw new RuntimeException("Unsupported entity: {$entity}");
        }

        return SyncJob::create([
            'connector_id' => $connector->id,
            'entity'       => $entity,
            'direction'    => $direction,
            'status'       => 'queued',
            'created_by'   => Auth::id(),
        ]);
    }

    public function run(SyncJob $job): SyncJob
    {
        $connector = $job->connector;
        if (!$connector) {
            $job->update(['status' => 'failed', 'error' => 'Connector not found.']);
            return $job;
        }

        $adapter = $this->adapters->for($connector->type);

        $job->update(['status' => 'running', 'started_at' => now()]);
        try {
            $this->dispatch($adapter, $connector, $job);
            $job->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);
            $connector->update(['last_sync_at' => now()]);
        } catch (Throwable $e) {
            $job->update([
                'status'      => 'failed',
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
        }

        return $job->fresh();
    }

    public function recentJobs(?int $connectorId = null, int $limit = 50): array
    {
        $q = SyncJob::query()
            ->with('connector:id,name,type')
            ->orderByDesc('id')
            ->limit($limit);
        if ($connectorId) $q->where('connector_id', $connectorId);
        return $q->get()->all();
    }

    private function dispatch($adapter, EcommerceConnector $connector, SyncJob $job): void
    {
        $entity = $job->entity;
        $dir    = $job->direction;

        if ($dir === 'pull' || $dir === 'bidirectional') {
            match ($entity) {
                'products'  => $adapter->pullProducts($connector, $job),
                'customers' => $adapter->pullCustomers($connector, $job),
                'orders'    => $adapter->pullOrders($connector, $job),
                'stock'     => null,
                default     => throw new RuntimeException("Cannot pull entity {$entity}"),
            };
        }
        if ($dir === 'push' || $dir === 'bidirectional') {
            match ($entity) {
                'stock'    => $adapter->pushStock($connector, $job),
                'products' => $adapter->pushProducts($connector, $job),
                default    => null,
            };
        }
    }
}
