<?php

namespace App\Modules\Stock\Controllers;

use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\Inventory;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class StockController extends Controller
{
    /**
     * Current stock report — all products with inventory, optionally filtered.
     * Admin: aggregates across all branches. Staff: scoped to their branch.
     */
    public function current(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Inventory::with(['product' => fn($q) => $q->with(['category', 'unit', 'brand'])])
            ->when($user->branch_id, fn($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('category_id'), fn($q) =>
                $q->whereHas('product', fn($p) => $p->where('category_id', $request->category_id))
            )
            ->when($request->filled('brand_id'), fn($q) =>
                $q->whereHas('product', fn($p) => $p->where('brand_id', $request->brand_id))
            )
            ->when($request->filled('product_id'), fn($q) =>
                $q->where('product_id', $request->product_id)
            );

        $items = $query->get()->filter(fn($inv) => $inv->product !== null);

        // Phase Y — pre-fetch a fast count map of in_stock serial rows so
        // serialized products report their authoritative quantity (the
        // number of physical devices currently flagged in_stock), not a
        // hand-maintained counter from the inventory table.
        //
        // Non-serialized products are unaffected — their quantity still
        // comes from the existing $inv->quantity aggregate.
        $serializedProductIds = $items->pluck('product')
            ->filter(fn ($p) => (bool) ($p->is_serialized ?? false))
            ->pluck('id')
            ->unique()
            ->values();

        $serialCounts = collect();
        if ($serializedProductIds->isNotEmpty()) {
            $serialCounts = \App\Modules\Serials\Models\ProductSerial::query()
                ->whereIn('product_id', $serializedProductIds)
                ->where('status', \App\Modules\Serials\Models\ProductSerial::STATUS_IN_STOCK)
                ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->selectRaw('product_id, COUNT(*) as c')
                ->groupBy('product_id')
                ->pluck('c', 'product_id');
        }

        // Group by product, summing quantities (handles multi-branch for admin)
        $rows = $items->groupBy('product_id')->map(function ($group) use ($serialCounts) {
            $inv      = $group->first();
            $product  = $inv->product;
            $isSerialized = (bool) ($product->is_serialized ?? false);

            $totalQty = $isSerialized
                ? (int) ($serialCounts[$inv->product_id] ?? 0)
                : $group->sum(fn($i) => (float) $i->quantity);

            $reorder  = (float) ($product->reorder_level ?? 0);

            return [
                'id'            => $inv->product_id,
                'sku'           => $product->sku           ?? '—',
                'name'          => $product->name          ?? '—',
                'image_url'     => $product->image
                                   ? Storage::url($product->image) : null,
                'category_name' => $product->category?->name ?? '—',
                'brand_name'    => $product->brand?->name    ?? '—',
                'unit_name'     => $product->unit?->name     ?? '',
                'unit_symbol'   => $product->unit?->symbol   ?? '',
                'is_serialized' => $isSerialized,
                'quantity'      => $totalQty,
                'reorder_level' => $reorder,
                'cost_price'    => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
                'stock_value'   => round($totalQty * (float) $product->cost_price, 2),
                'low_stock'     => $totalQty > 0 && $reorder > 0 && $totalQty <= $reorder,
                'out_of_stock'  => $totalQty <= 0,
            ];
        })->sortBy('name')->values();

        return response()->json([
            'data'    => $rows,
            'summary' => [
                'total_products'  => $rows->count(),
                'total_value'     => round($rows->sum('stock_value'), 2),
                'low_stock_count' => $rows->where('low_stock', true)->count(),
                'out_of_stock'    => $rows->where('out_of_stock', true)->count(),
            ],
        ]);
    }

    /**
     * Filter options for the frontend dropdowns.
     */
    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'categories' => ProductCategory::active()->orderBy('name')->get(['id', 'name']),
            'brands'     => Brand::active()->orderBy('name')->get(['id', 'name']),
            'products'   => Product::active()->orderBy('name')->get(['id', 'sku', 'name'])
                ->map(fn($p) => ['id' => $p->id, 'text' => "{$p->sku} — {$p->name}"]),
        ]);
    }
}
