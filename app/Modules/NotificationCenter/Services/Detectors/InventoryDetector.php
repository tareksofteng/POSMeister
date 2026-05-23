<?php

namespace App\Modules\NotificationCenter\Services\Detectors;

use App\Modules\NotificationCenter\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryDetector
{
    public function __construct(private SmartNotificationService $notify) {}

    public function run(): int
    {
        $pushed = 0;
        $pushed += $this->lowStock();
        $pushed += $this->outOfStock();
        $pushed += $this->deadStock();
        return $pushed;
    }

    private function lowStock(): int
    {
        if (!Schema::hasTable('inventory') || !Schema::hasTable('products')) return 0;

        $count = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>', 0)
            ->where('products.is_active', true)
            ->distinct()
            ->count('products.id');

        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'inventory',
            'code'          => 'inventory.low_stock',
            'severity'      => $count > 20 ? 'danger' : 'warning',
            'urgency'       => min(50 + $count, 95),
            'title'         => "{$count} products below reorder level",
            'message'       => 'Create a purchase order before they sell out.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'inventory.low_stock',
            'cooldown_minutes' => 240,
            'actions'       => [
                ['label' => 'menu.reorderSuggestions', 'route' => 'inventory-reorder', 'type' => 'primary'],
            ],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function outOfStock(): int
    {
        if (!Schema::hasTable('inventory')) return 0;
        $count = DB::table('inventory')->where('quantity', '<=', 0)->distinct()->count('product_id');
        if ($count === 0) return 0;

        $this->notify->push([
            'category'      => 'inventory',
            'code'          => 'inventory.out_of_stock',
            'severity'      => 'danger',
            'urgency'       => 80,
            'title'         => "{$count} products out of stock",
            'message'       => 'These products cannot be sold until restocked.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'inventory.out_of_stock',
            'cooldown_minutes' => 360,
            'actions'       => [['label' => 'menu.stockOverview', 'route' => 'inventory', 'type' => 'primary']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }

    private function deadStock(): int
    {
        if (!Schema::hasTable('sales_items') || !Schema::hasTable('products')) return 0;

        // Products with stock > 0 and no sale in 90 days
        $count = DB::table('products')
            ->leftJoin('sales_items', function ($j) {
                $j->on('sales_items.product_id', '=', 'products.id')
                  ->where('sales_items.created_at', '>=', now()->subDays(90));
            })
            ->where('products.is_active', true)
            ->whereNull('sales_items.id')
            ->distinct()
            ->count('products.id');

        if ($count < 10) return 0;  // ignore noise

        $this->notify->push([
            'category'      => 'inventory',
            'code'          => 'inventory.dead_stock',
            'severity'      => 'warning',
            'urgency'       => 40,
            'title'         => "{$count} products with no sales in 90 days",
            'message'       => 'Consider running a promotion or clearance.',
            'audience_role' => 'admin',
            'dedupe_key'    => 'inventory.dead_stock',
            'cooldown_minutes' => 1440,    // daily reminder
            'actions'       => [['label' => 'menu.deadStock', 'route' => 'inventory-dead-stock']],
            'meta'          => ['count' => $count],
        ]);
        return 1;
    }
}
