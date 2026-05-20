<?php

namespace App\Modules\OMS\Couriers\Adapters;

use App\Modules\OMS\Couriers\CourierProvider;
use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;

/**
 * DHL adapter — targets the eCommerce / Paket API used in the DACH region.
 * Real signing + EU label fetching to be wired when the contract is in place.
 */
class DHLAdapter implements CourierProvider
{
    public function code(): string
    {
        return 'dhl';
    }

    public function createShipment(Courier $courier, Order $order): array
    {
        return [
            'tracking_number' => 'DHL' . str_pad((string) $order->id, 11, '0', STR_PAD_LEFT),
            'label_url'       => null,
            'shipping_cost'   => (float) ($courier->settings['default_cost'] ?? 5.99),
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
