<?php

namespace App\Modules\OMS\Ecommerce;

use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;

/**
 * Contract every e-commerce platform integration implements. Each method
 * receives the connector record (with credentials + settings) and the
 * sync_job row so adapters can record progress incrementally.
 *
 * Adapters MUST be idempotent: re-running a sync should converge, not
 * duplicate records. Map external ids via `external_reference` on local
 * tables.
 */
interface EcommerceConnectorAdapter
{
    /** Stable identifier matching `ecommerce_connectors.type`. */
    public function type(): string;

    /** Pull remote → local. Updates sync_job counters as it goes. */
    public function pullProducts(EcommerceConnector $connector, SyncJob $job): void;
    public function pullCustomers(EcommerceConnector $connector, SyncJob $job): void;
    public function pullOrders(EcommerceConnector $connector, SyncJob $job): void;

    /** Push local → remote. */
    public function pushStock(EcommerceConnector $connector, SyncJob $job): void;
    public function pushProducts(EcommerceConnector $connector, SyncJob $job): void;
}
