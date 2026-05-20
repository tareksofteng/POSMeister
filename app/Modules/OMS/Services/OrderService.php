<?php

namespace App\Modules\OMS\Services;

use App\Modules\OMS\Models\Order;
use App\Modules\OMS\Models\OrderItem;
use App\Modules\OMS\Models\OrderStatusLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Order lifecycle owner. Status transitions are recorded to
 * order_status_logs in the same transaction as the state change, so the
 * delivery timeline is always trustworthy.
 *
 * Valid transitions:
 *   pending   → confirmed | cancelled
 *   confirmed → packed    | cancelled
 *   packed    → shipped   | cancelled
 *   shipped   → delivered | returned
 *   delivered → returned
 */
class OrderService
{
    private const ALLOWED_TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['packed', 'cancelled'],
        'packed'    => ['shipped', 'cancelled'],
        'shipped'   => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned'  => [],
    ];

    public function create(array $payload, array $items): Order
    {
        return DB::transaction(function () use ($payload, $items) {
            if (empty($items)) {
                throw new RuntimeException('Order must contain at least one item.');
            }

            $subtotal = 0;
            $vat      = 0;
            foreach ($items as $i) {
                $qty = (float) ($i['quantity'] ?? 0);
                $price = (float) ($i['unit_price'] ?? 0);
                $tax = (float) ($i['tax_rate'] ?? 0);
                $line = $qty * $price;
                $subtotal += $line;
                $vat      += $line * ($tax / 100);
            }
            $discount = (float) ($payload['discount']      ?? 0);
            $shipping = (float) ($payload['shipping_cost'] ?? 0);
            $total    = $subtotal - $discount + $vat + $shipping;

            $order = Order::create([
                'order_number'     => $payload['order_number'] ?? $this->nextNumber(),
                'customer_id'      => $payload['customer_id'] ?? null,
                'branch_id'        => $payload['branch_id'] ?? Auth::user()?->branch_id,
                'source'           => $payload['source'] ?? 'manual',
                'status'           => 'pending',
                'payment_status'   => $payload['payment_status'] ?? 'unpaid',
                'payment_method'   => $payload['payment_method'] ?? 'cod',
                'subtotal'         => round($subtotal, 2),
                'discount'         => $discount,
                'shipping_cost'    => $shipping,
                'vat_amount'       => round($vat, 2),
                'total'            => round($total, 2),
                'paid_amount'      => (float) ($payload['paid_amount'] ?? 0),
                'customer_name'    => $payload['customer_name']    ?? null,
                'customer_phone'   => $payload['customer_phone']   ?? null,
                'delivery_address' => $payload['delivery_address'] ?? null,
                'delivery_city'    => $payload['delivery_city']    ?? null,
                'delivery_zip'     => $payload['delivery_zip']     ?? null,
                'notes'            => $payload['notes']            ?? null,
                'external_reference' => $payload['external_reference'] ?? null,
                'placed_at'        => now(),
            ]);

            foreach ($items as $i) {
                $qty = (float) $i['quantity'];
                $price = (float) $i['unit_price'];
                $line = $qty * $price;
                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => (int) $i['product_id'],
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'cost_price'  => (float) ($i['cost_price'] ?? 0),
                    'tax_rate'    => (float) ($i['tax_rate'] ?? 0),
                    'line_total'  => round($line, 2),
                ]);
            }

            $this->log($order, null, 'pending', 'Order placed');
            return $order->load('items');
        });
    }

    public function transition(Order $order, string $to, ?string $note = null): Order
    {
        $allowed = self::ALLOWED_TRANSITIONS[$order->status] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new RuntimeException("Cannot transition from {$order->status} → {$to}.");
        }

        return DB::transaction(function () use ($order, $to, $note) {
            $from = $order->status;
            $order->status = $to;

            $timestampColumn = match ($to) {
                'confirmed' => 'confirmed_at',
                'packed'    => 'packed_at',
                'shipped'   => 'shipped_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
                default     => null,
            };
            if ($timestampColumn) $order->{$timestampColumn} = now();

            if ($to === 'delivered') {
                foreach ($order->items as $it) {
                    if ($it->fulfilled_qty < $it->quantity) {
                        $it->fulfilled_qty = $it->quantity;
                        $it->save();
                    }
                }
            }

            $order->save();
            $this->log($order, $from, $to, $note);
            return $order->fresh(['items', 'logs']);
        });
    }

    public function fulfilPartial(Order $order, array $itemFulfilments): Order
    {
        return DB::transaction(function () use ($order, $itemFulfilments) {
            foreach ($itemFulfilments as $row) {
                $item = $order->items()->where('id', $row['item_id'])->lockForUpdate()->first();
                if (!$item) continue;
                $newFulfilled = min((float) $item->quantity, (float) $item->fulfilled_qty + (float) $row['qty']);
                $item->fulfilled_qty = $newFulfilled;
                $item->save();
            }
            $order->touch();
            return $order->fresh('items');
        });
    }

    public function markPaid(Order $order, float $amount, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $amount, $note) {
            $order->paid_amount = (float) $order->paid_amount + $amount;
            $order->payment_status = $order->paid_amount >= (float) $order->total ? 'paid'
                : ($order->paid_amount > 0 ? 'partial' : 'unpaid');
            $order->save();
            $this->log($order, $order->status, $order->status, $note ?: "Payment recorded: {$amount}");
            return $order->fresh();
        });
    }

    /**
     * OMS dashboard KPIs — fulfilment rate, avg delivery hours, delayed
     * orders, return rate.
     */
    public function dashboard(?int $branchId = null): array
    {
        $scope = $this->resolveBranchScope($branchId);
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();

        $base = Order::query()
            ->whereDate('placed_at', '>=', $monthStart)
            ->when($scope, fn($q) => $q->where('branch_id', $scope));

        $total       = (clone $base)->count();
        $delivered   = (clone $base)->where('status', 'delivered')->count();
        $cancelled   = (clone $base)->where('status', 'cancelled')->count();
        $returned    = (clone $base)->where('status', 'returned')->count();
        $openOrders  = Order::query()
            ->whereIn('status', ['pending', 'confirmed', 'packed', 'shipped'])
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->count();

        $delayed = Order::query()
            ->whereIn('status', ['pending', 'confirmed', 'packed'])
            ->whereDate('placed_at', '<', $today->copy()->subDays(2)->toDateString())
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->count();

        $avgHours = Order::query()
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '>=', $monthStart)
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, placed_at, delivered_at)) as h')
            ->value('h');

        $byStatus = Order::query()
            ->when($scope, fn($q) => $q->where('branch_id', $scope))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'period'              => ['from' => $monthStart, 'to' => $today->toDateString()],
            'total_month'         => $total,
            'delivered_month'     => $delivered,
            'cancelled_month'     => $cancelled,
            'returned_month'      => $returned,
            'open_orders'         => $openOrders,
            'delayed_orders'      => $delayed,
            'fulfilment_rate'     => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
            'return_rate'         => $total > 0 ? round(($returned  / $total) * 100, 1) : 0,
            'avg_delivery_hours'  => $avgHours !== null ? round((float) $avgHours, 1) : null,
            'by_status'           => $byStatus->toArray(),
        ];
    }

    public function nextNumber(): string
    {
        $year = (int) date('Y');
        $last = Order::where('order_number', 'like', "ORD-{$year}-%")
            ->orderByDesc('id')->value('order_number');
        $seq = 1;
        if ($last && preg_match('/ORD-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('ORD-%d-%05d', $year, $seq);
    }

    private function log(Order $order, ?string $from, string $to, ?string $note): void
    {
        OrderStatusLog::create([
            'order_id'    => $order->id,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $note,
            'created_by'  => Auth::id(),
            'created_at'  => now(),
        ]);
    }

    private function resolveBranchScope(?int $branchId): ?int
    {
        if (Auth::user()?->role === 'admin') return $branchId;
        return Auth::user()?->branch_id;
    }
}
