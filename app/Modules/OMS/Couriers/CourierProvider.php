<?php

namespace App\Modules\OMS\Couriers;

use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;

/**
 * Every courier integration implements this interface. The shipment service
 * holds the provider abstractly so swapping providers later (or adding a new
 * one) is just dropping in a new adapter and registering it in
 * CourierProviderRegistry.
 */
interface CourierProvider
{
    /** Stable identifier matching `couriers.code` (e.g. "pathao"). */
    public function code(): string;

    /**
     * Create a shipment on the provider side. Returns a structured payload:
     *   [
     *     'tracking_number' => 'string',
     *     'label_url'       => 'string|null',
     *     'shipping_cost'   => float,
     *     'status'          => 'created|pending|...',
     *     'raw'             => mixed,
     *   ]
     */
    public function createShipment(Courier $courier, Order $order): array;

    /**
     * Poll the provider for current tracking status. Returns a status string
     * mapped to our internal Shipment::status set.
     */
    public function fetchStatus(Courier $courier, string $trackingNumber): string;

    /**
     * Best-effort cancel. Returns true on success.
     */
    public function cancelShipment(Courier $courier, string $trackingNumber): bool;
}
