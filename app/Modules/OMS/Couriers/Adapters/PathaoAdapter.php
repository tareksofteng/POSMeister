<?php

namespace App\Modules\OMS\Couriers\Adapters;

use App\Modules\OMS\Couriers\CourierProvider;
use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;
use Illuminate\Support\Str;

/**
 * Pathao Bangladesh adapter. Real API calls intentionally NOT wired —
 * this is the contract + a deterministic local stub so the UI can be
 * exercised before the credentials arrive.
 *
 * To go live: replace the stubbed methods with HTTP::withToken() calls
 * against the Pathao merchant API. Token refresh and signing live here.
 */
class PathaoAdapter implements CourierProvider
{
    public function code(): string
    {
        return 'pathao';
    }

    public function createShipment(Courier $courier, Order $order): array
    {
        // Real call: POST /aladdin/api/v1/orders with the merchant token.
        // For now we mint a deterministic tracking number from the order.
        $tracking = 'PTH' . str_pad((string) $order->id, 9, '0', STR_PAD_LEFT);

        return [
            'tracking_number' => $tracking,
            'label_url'       => null,
            'shipping_cost'   => (float) $courier->settings['default_cost'] ?? 60.0,
            'status'          => 'created',
            'raw'             => ['stub' => true, 'order' => $order->order_number],
        ];
    }

    public function fetchStatus(Courier $courier, string $trackingNumber): string
    {
        // Real call: GET /aladdin/api/v1/orders/{tracking}/info.
        return 'in_transit';
    }

    public function cancelShipment(Courier $courier, string $trackingNumber): bool
    {
        // Real call: POST /aladdin/api/v1/orders/{tracking}/cancel.
        return true;
    }
}
