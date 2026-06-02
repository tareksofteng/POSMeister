<?php

namespace App\Modules\SystemOps\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk payload shipped to PWA clients so they can run the POS during
 * an internet outage. Kept intentionally small per row — anything the
 * cashier doesn't need to ring up a sale is omitted to keep the
 * IndexedDB cache fast on low-end Android tablets.
 */
class OfflineSnapshotService
{
    public function __construct(private SettingsService $settings) {}

    public function build(?int $branchId = null): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'settings'     => $this->settingsPayload(),
            'branches'     => $this->branches(),
            'tax_rules'    => $this->taxRules(),
            'products'     => $this->products($branchId),
            'customers'    => $this->customers(),
            // ── Lookup tables needed to populate dropdowns when the
            //    cashier creates a new product or purchase offline.
            'suppliers'    => $this->suppliers(),
            'categories'   => $this->categories(),
            'brands'       => $this->brands(),
            'units'        => $this->units(),
            // ── Recent transactional history so the cashier can browse the
            //    Sales / Purchases lists while offline. Limited to keep the
            //    payload small; older history requires reconnecting.
            'recent_sales'     => $this->recentSales(),
            'recent_purchases' => $this->recentPurchases(),
        ];
    }

    private function settingsPayload(): array
    {
        $s = $this->settings->get();
        return [
            'company_name'    => $s->company_name    ?? null,
            'currency_symbol' => $s->currency_symbol ?? '€',
            'currency_code'   => $s->currency_code   ?? 'EUR',
            'locale'          => $s->default_locale  ?? 'de',
            'address'         => $s->address         ?? null,
            'phone'           => $s->phone           ?? null,
            'email'           => $s->email           ?? null,
            'logo_url'        => $s->logo_url        ?? null,
            'invoice_footer'  => $s->invoice_footer  ?? null,
        ];
    }

    private function branches(): array
    {
        return Branch::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn($b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name])
            ->all();
    }

    private function taxRules(): array
    {
        // Static rates currently exposed by the UI (0 / 7 / 19). Returned
        // here so a future settings panel can drive them without a code
        // change on the client.
        return [
            ['code' => 'zero',     'rate' => 0,  'label' => '0%'],
            ['code' => 'reduced',  'rate' => 7,  'label' => '7%'],
            ['code' => 'standard', 'rate' => 19, 'label' => '19%'],
        ];
    }

    private function products(?int $branchId): array
    {
        // Stock comes from the `stock` table if it exists; default 0
        // otherwise (Phase D introduced stock; pre-D installs stay safe).
        $hasStock = Schema::hasTable('stock');

        $query = Product::query()
            ->where('is_active', true)
            ->select(['id','sku','name','barcode','cost_price','selling_price','tax_rate','unit_id','image','category_id','brand_id','is_service']);

        return $query->orderBy('name')->get()->map(function ($p) use ($hasStock, $branchId) {
            $stock = 0;
            if ($hasStock) {
                $q = DB::table('stock')->where('product_id', $p->id);
                if ($branchId) $q->where('branch_id', $branchId);
                $stock = (float) ($q->sum('quantity') ?? 0);
            }
            return [
                'id'            => $p->id,
                'sku'           => $p->sku,
                'name'          => $p->name,
                'barcode'       => $p->barcode,
                'cost_price'    => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
                'tax_rate'      => (float) $p->tax_rate,
                'unit_id'       => $p->unit_id,
                'image'         => $p->image,
                'category_id'   => $p->category_id,
                'brand_id'      => $p->brand_id,
                'is_service'    => (bool) $p->is_service,
                'stock'         => $stock,
            ];
        })->all();
    }

    private function customers(): array
    {
        if (!Schema::hasTable('customers')) return [];

        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(5000)
            ->get(['id','code','name','phone','email','address'])
            ->map(fn($c) => [
                'id'      => $c->id,
                'code'    => $c->code,
                'name'    => $c->name,
                'phone'   => $c->phone,
                'email'   => $c->email,
                'address' => $c->address,
            ])
            ->all();
    }

    private function suppliers(): array
    {
        if (!Schema::hasTable('suppliers')) return [];

        return Supplier::query()
            ->orderBy('name')
            ->get(['id','code','name','phone','email','address'])
            ->map(fn($s) => [
                'id'      => $s->id,
                'code'    => $s->code,
                'name'    => $s->name,
                'phone'   => $s->phone,
                'email'   => $s->email,
                'address' => $s->address,
            ])
            ->all();
    }

    private function categories(): array
    {
        // Live data is in `product_categories` (the model is named
        // ProductCategory). The legacy `categories` table from an earlier
        // prototype doesn't exist on production, which is why the previous
        // version of this method returned an empty list.
        if (!Schema::hasTable('product_categories')) return [];
        return ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id','name'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    private function brands(): array
    {
        if (!Schema::hasTable('brands')) return [];
        return Brand::query()
            ->orderBy('name')
            ->get(['id','name'])
            ->map(fn($b) => ['id' => $b->id, 'name' => $b->name])
            ->all();
    }

    private function units(): array
    {
        if (!Schema::hasTable('units')) return [];
        return Unit::query()
            ->orderBy('name')
            ->get(['id','name','symbol'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'symbol' => $u->symbol])
            ->all();
    }

    private function recentSales(): array
    {
        if (!Schema::hasTable('sales')) return [];
        return Sale::query()
            ->withCount('items')
            ->latest('sale_date')
            ->latest('id')
            ->limit(200)
            ->get([
                'id','sale_number','sale_date','customer_id','customer_name',
                'customer_phone','subtotal','discount_amount','vat_amount',
                'freight_amount','grand_total','total_paid','due_amount',
                'status','branch_id',
            ])
            ->map(fn($s) => [
                'id'              => $s->id,
                'sale_number'     => $s->sale_number,
                'sale_date'       => $s->sale_date?->toDateString(),
                'customer_id'     => $s->customer_id,
                'customer_name'   => $s->customer_name,
                'customer_phone'  => $s->customer_phone,
                'subtotal'        => (float) $s->subtotal,
                'discount_amount' => (float) $s->discount_amount,
                'vat_amount'      => (float) $s->vat_amount,
                'freight_amount'  => (float) $s->freight_amount,
                'grand_total'     => (float) $s->grand_total,
                'total_paid'      => (float) $s->total_paid,
                'due_amount'      => (float) $s->due_amount,
                'status'          => $s->status,
                'branch_id'       => $s->branch_id,
                'items_count'     => $s->items_count ?? 0,
            ])
            ->all();
    }

    private function recentPurchases(): array
    {
        if (!Schema::hasTable('purchases')) return [];
        return Purchase::query()
            ->with(['supplier:id,name'])
            ->withCount('items')
            ->latest('purchase_date')
            ->latest('id')
            ->limit(200)
            ->get([
                'id','purchase_number','purchase_date','supplier_id','branch_id',
                'reference','subtotal','discount_amount','vat_amount',
                'freight_amount','total_amount','status',
            ])
            ->map(fn($p) => [
                'id'              => $p->id,
                'purchase_number' => $p->purchase_number,
                'purchase_date'   => $p->purchase_date?->toDateString(),
                'supplier_id'     => $p->supplier_id,
                'supplier_name'   => $p->supplier?->name,
                'branch_id'       => $p->branch_id,
                'reference'       => $p->reference,
                'subtotal'        => (float) $p->subtotal,
                'discount_amount' => (float) $p->discount_amount,
                'vat_amount'      => (float) $p->vat_amount,
                'freight_amount'  => (float) $p->freight_amount,
                'total_amount'    => (float) $p->total_amount,
                'status'          => $p->status,
                'items_count'     => $p->items_count ?? 0,
            ])
            ->all();
    }
}
