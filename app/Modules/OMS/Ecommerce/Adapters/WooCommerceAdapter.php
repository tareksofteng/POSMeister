<?php

namespace App\Modules\OMS\Ecommerce\Adapters;

use App\Modules\OMS\Ecommerce\EcommerceConnectorAdapter;
use App\Modules\OMS\Models\EcommerceConnector;
use App\Modules\OMS\Models\SyncJob;

/**
 * WooCommerce REST v3 adapter. Real HTTP calls (with Basic Auth on
 * consumer-key/secret) belong here. For now the methods are no-ops that
 * advance the sync_job state machine, so the rest of the system can be
 * exercised end-to-end.
 */
class WooCommerceAdapter implements EcommerceConnectorAdapter
{
    public function type(): string { return 'woocommerce'; }

    public function pullProducts(EcommerceConnector $connector, SyncJob $job): void
    {
        // Real impl: GET {api_url}/wp-json/wc/v3/products?per_page=100&page=X
        // Map remote fields → products + external_reference.
    }

    public function pullCustomers(EcommerceConnector $connector, SyncJob $job): void
    {
        // GET /wp-json/wc/v3/customers
    }

    public function pullOrders(EcommerceConnector $connector, SyncJob $job): void
    {
        // GET /wp-json/wc/v3/orders?status=processing
    }

    public function pushStock(EcommerceConnector $connector, SyncJob $job): void
    {
        // PUT /wp-json/wc/v3/products/{id} with stock_quantity
    }

    public function pushProducts(EcommerceConnector $connector, SyncJob $job): void
    {
        // POST/PUT /wp-json/wc/v3/products
    }
}
