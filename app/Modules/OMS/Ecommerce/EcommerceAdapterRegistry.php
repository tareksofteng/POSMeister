<?php

namespace App\Modules\OMS\Ecommerce;

use RuntimeException;

class EcommerceAdapterRegistry
{
    /** @var array<string, EcommerceConnectorAdapter> */
    private array $adapters = [];

    public function __construct()
    {
        $this->register(new Adapters\WooCommerceAdapter());
        $this->register(new Adapters\ShopifyAdapter());
        $this->register(new Adapters\CustomStoreAdapter());
    }

    public function register(EcommerceConnectorAdapter $adapter): void
    {
        $this->adapters[$adapter->type()] = $adapter;
    }

    public function for(string $type): EcommerceConnectorAdapter
    {
        if (!isset($this->adapters[$type])) {
            throw new RuntimeException("No e-commerce adapter registered for type '{$type}'.");
        }
        return $this->adapters[$type];
    }

    public function types(): array
    {
        return array_keys($this->adapters);
    }
}
