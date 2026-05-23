<?php

namespace App\Modules\SystemOps\Services;

use App\Modules\Branch\Models\Branch;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\Customer;
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
}
