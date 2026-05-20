<?php

namespace App\Modules\OMS\Couriers;

use RuntimeException;

/**
 * Map of courier code → adapter instance. Add new providers by registering
 * them here (or via service-container binding). Callers never instantiate
 * adapters directly.
 */
class CourierProviderRegistry
{
    /** @var array<string, CourierProvider> */
    private array $providers = [];

    public function __construct()
    {
        $this->register(new Adapters\PathaoAdapter());
        $this->register(new Adapters\RedxAdapter());
        $this->register(new Adapters\DHLAdapter());
    }

    public function register(CourierProvider $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function for(string $code): CourierProvider
    {
        if (!isset($this->providers[$code])) {
            throw new RuntimeException("No courier adapter registered for code '{$code}'.");
        }
        return $this->providers[$code];
    }

    public function codes(): array
    {
        return array_keys($this->providers);
    }
}
