<?php

namespace App\Modules\OMS\Services;

use App\Modules\OMS\Couriers\CourierProviderRegistry;
use App\Modules\OMS\Models\Courier;
use App\Modules\OMS\Models\Order;
use App\Modules\OMS\Models\Shipment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Wraps the courier adapter behind a stable in-app API. Callers never
 * touch HTTP or provider-specific quirks — they just say "ship this order
 * with this courier" and inspect the resulting Shipment row.
 */
class ShipmentService
{
    public function __construct(
        private readonly CourierProviderRegistry $couriers,
        private readonly OrderService $orders,
    ) {}

    public function createForOrder(Order $order, Courier $courier): Shipment
    {
        if (!$courier->is_active) {
            throw new RuntimeException("Courier '{$courier->name}' is not active.");
        }
        if (!in_array($order->status, ['confirmed', 'packed'], true)) {
            throw new RuntimeException('Order must be confirmed or packed before shipping.');
        }

        return DB::transaction(function () use ($order, $courier) {
            $existing = Shipment::where('order_id', $order->id)->first();
            if ($existing && !in_array($existing->status, ['cancelled', 'failed'], true)) {
                return $existing;
            }

            $shipment = Shipment::create([
                'order_id'      => $order->id,
                'courier_id'    => $courier->id,
                'status'        => 'pending',
                'shipping_cost' => (float) $order->shipping_cost,
                'created_by'    => Auth::id(),
            ]);

            try {
                $provider = $this->couriers->for($courier->code);
                $result = $provider->createShipment($courier, $order);

                $shipment->update([
                    'tracking_number'  => $result['tracking_number'] ?? null,
                    'label_url'        => $result['label_url']       ?? null,
                    'shipping_cost'    => (float) ($result['shipping_cost'] ?? $shipment->shipping_cost),
                    'status'           => $result['status'] ?? 'created',
                    'dispatched_at'    => now(),
                    'provider_payload' => $result['raw'] ?? null,
                ]);
            } catch (Throwable $e) {
                $shipment->update([
                    'status'     => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Auto-advance the order once a successful shipment is created.
            $this->orders->transition($order->fresh(), 'shipped', "Shipped via {$courier->name}");

            return $shipment->fresh();
        });
    }

    public function refreshStatus(Shipment $shipment): Shipment
    {
        if (!$shipment->courier || !$shipment->tracking_number) {
            return $shipment;
        }
        $provider = $this->couriers->for($shipment->courier->code);
        try {
            $status = $provider->fetchStatus($shipment->courier, $shipment->tracking_number);
            $shipment->status = $status;
            if ($status === 'delivered' && !$shipment->delivered_at) {
                $shipment->delivered_at = now();
            }
            $shipment->save();

            if ($status === 'delivered' && $shipment->order && $shipment->order->status === 'shipped') {
                $this->orders->transition($shipment->order, 'delivered', 'Delivered by carrier');
            }
        } catch (Throwable $e) {
            $shipment->update(['last_error' => $e->getMessage()]);
        }
        return $shipment->fresh();
    }

    public function cancel(Shipment $shipment, ?string $reason = null): Shipment
    {
        if (!$shipment->courier || !$shipment->tracking_number) {
            $shipment->update(['status' => 'cancelled', 'last_error' => $reason]);
            return $shipment;
        }
        $provider = $this->couriers->for($shipment->courier->code);
        $ok = false;
        try {
            $ok = $provider->cancelShipment($shipment->courier, $shipment->tracking_number);
        } catch (Throwable $e) {
            $shipment->update(['last_error' => $e->getMessage()]);
        }
        $shipment->update(['status' => $ok ? 'cancelled' : 'failed']);
        return $shipment->fresh();
    }
}
