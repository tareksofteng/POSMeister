<?php

namespace App\Modules\OMS\Ecommerce\Adapters;

use App\Modules\OMS\Ecommerce\EcommerceConnectorAdapter;
use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;

/**
 * Shopify Admin API adapter. Targets the 2024-10 REST endpoints with the
 * X-Shopify-Access-Token header. Stubbed identically to WooCommerceAdapter
 * so the orchestrator doesn't care which storefront it's talking to.
 */
class ShopifyAdapter implements EcommerceConnectorAdapter
{
    public function type(): string { return 'shopify'; }

    public function pullProducts(EcommerceConnector $connector, SyncJob $job): void {}
    public function pullCustomers(EcommerceConnector $connector, SyncJob $job): void {}
    public function pullOrders(EcommerceConnector $connector, SyncJob $job): void {}
    public function pushStock(EcommerceConnector $connector, SyncJob $job): void {}
    public function pushProducts(EcommerceConnector $connector, SyncJob $job): void {}
}
