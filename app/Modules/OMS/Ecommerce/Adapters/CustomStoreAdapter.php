<?php

namespace App\Modules\OMS\Ecommerce\Adapters;

use App\Modules\OMS\Ecommerce\EcommerceConnectorAdapter;
use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;

/**
 * Adapter for a Laravel-based custom storefront speaking a simple
 * JSON-over-HTTP protocol. Use when the store is built in-house and we
 * control both ends.
 */
class CustomStoreAdapter implements EcommerceConnectorAdapter
{
    public function type(): string { return 'custom'; }

    public function pullProducts(EcommerceConnector $connector, SyncJob $job): void {}
    public function pullCustomers(EcommerceConnector $connector, SyncJob $job): void {}
    public function pullOrders(EcommerceConnector $connector, SyncJob $job): void {}
    public function pushStock(EcommerceConnector $connector, SyncJob $job): void {}
    public function pushProducts(EcommerceConnector $connector, SyncJob $job): void {}
}
