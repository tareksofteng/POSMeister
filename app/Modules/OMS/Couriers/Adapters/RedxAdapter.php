<?php

namespace App\Modules\OMS\Couriers\Adapters;

use App\Modules\OMS\Couriers\CourierProvider;
use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;

/**
 * Redx Bangladesh adapter. Real HTTP layer to be added once API keys are
 * provisioned. Mirrors PathaoAdapter shape so the calling code stays
 * identical.
 */
class RedxAdapter implements CourierProvider
{
    public function code(): string
    {
        return 'redx';
    }

    public function createShipment(Courier $courier, Order $order): array
    {
        return [
            'tracking_number' => 'REDX' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
            'label_url'       => null,
            'shipping_cost'   => (float) ($courier->settings['default_cost'] ?? 70.0),
            'status'          => 'created',
            'raw'             => ['stub' => true, 'order' => $order->order_number],
        ];
    }

    public function fetchStatus(Courier $courier, string $trackingNumber): string
    {
        return 'in_transit';
    }

    public function cancelShipment(Courier $courier, string $trackingNumber): bool
    {
        return true;
    }
}
