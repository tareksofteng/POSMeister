<?php

namespace App\Modules\Product\Services;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Product::with(['category', 'brand', 'unit'])
            ->when(
                $filters['search'] ?? null,
                fn($q, $v) =>
                $q->where(
                    fn($sub) => $sub
                        ->where('name', 'like', "%{$v}%")
                        ->orWhere('sku', 'like', "%{$v}%")
                        ->orWhere('barcode', 'like', "%{$v}%")
                )
            )
            ->when($filters['category_id'] ?? null, fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['brand_id'] ?? null,    fn($q, $v) => $q->where('brand_id', $v))
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn($q) =>
                $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function all(): Collection
    {
        return Product::active()
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'cost_price', 'tax_rate', 'unit_id', 'image']);
    }

    public function search(string $term): Collection
    {
        // Stock is included as a denormalised subquery so cashiers see
        // live on-hand quantity in the search dropdown without an extra
        // round-trip. Branch-scope respected via the active user's branch.
        $base = Product::with('unit')
            ->active()
            ->withSum(['inventory as stock' => fn ($q) => $this->branchScopedInventory($q)], 'quantity');

        // `is_serialized` is required by PurchaseFormView / PosView /
        // SaleFormView so they can swap the qty input for the "Add Serials"
        // (purchase) or "Select Serials" (sale) modal trigger. Without
        // this column the dropdown returns the product but the flag is
        // undefined → the trigger never renders.
        $cols = ['id', 'sku', 'name', 'selling_price', 'cost_price', 'tax_rate',
                 'unit_id', 'image', 'reorder_level', 'is_serialized'];

        if ($term === '') {
            return $base->latest()->limit(100)->get($cols);
        }

        return $base
            ->where(
                fn($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term)
            )
            ->limit(50)
            ->get($cols);
    }

    private function branchScopedInventory($q)
    {
        // Topbar workspace context — null when admin is in the Main
        // Branch / "All branches" super-workspace (sums every branch's
        // inventory together), specific id otherwise.
        $ctx      = app(BranchContextService::class);
        $branchId = $ctx->isMainBranch() ? null : $ctx->current();
        return $branchId ? $q->where('branch_id', $branchId) : $q;
    }

    public function store(array $data): Product
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku();
        }

        if ($data['is_service'] ?? false) {
            $data['cost_price'] = 0;
        }

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if ($data['is_service'] ?? false) {
            $data['cost_price'] = 0;
        }

        // Phase Y — once a serialized product has any device history,
        // flipping is_serialized back to false would orphan the
        // existing product_serials rows and break inventory math.
        // Drop the field from the payload so the original value sticks.
        if (array_key_exists('is_serialized', $data)
            && (bool) $data['is_serialized'] !== (bool) $product->is_serialized
            && $product->isSerializationLocked()
        ) {
            unset($data['is_serialized']);
        }

        $product->update($data);
        return $product->fresh(['category', 'brand', 'unit']);
    }

    public function toggleStatus(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function generateSku(): string
    {
        $next = (Product::withTrashed()->max('id') ?? 0) + 1;
        return 'P-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
