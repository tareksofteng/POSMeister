<?php

namespace App\Modules\Serials\Services;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\Product\Models\Product;
use App\Modules\Serials\Models\ProductSerial;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 |--------------------------------------------------------------------------
 | SerialTrackingService — Phase Y public surface
 |--------------------------------------------------------------------------
 |
 | Thin enough that controllers stay thin, fat enough that the rules
 | live in one place. Three pillars:
 |
 |   1. ATTACH    — bulk-attach freshly-received serials at purchase time
 |                  (called by PurchaseService::receive() once the
 |                  purchase document is committed). Enforces:
 |                    * count(serials) == purchase line quantity
 |                    * no duplicates inside the same submission
 |                    * no collision with any existing serial in the system
 |                    * product is flagged is_serialized = true
 |
 |   2. PICK      — when a serialized line is added to a sale, the cashier
 |                  picks which exact serials leave stock. Enforces:
 |                    * every picked serial exists, is in_stock, belongs to
 |                      the right product, and lives on the cashier's
 |                      branch (if branch scoping is enabled)
 |                    * picked count > 0
 |                    * marks each serial sold via SerialMovementService
 |
 |   3. RETURN    — purchase return and sales return both go through one
 |                  validated path. Sales-return rule (resellable vs not)
 |                  is delegated to a setting / policy.
 |
 | All three pillars are atomic: a single transaction per call, no half
 | states. Inventory count is *derived* from product_serials, never
 | hand-bumped — that's what Inventory::quantity should fall back to via
 | the InventoryService rather than being set by this service directly.
 */
class SerialTrackingService
{
    public function __construct(
        protected SerialMovementService $movements,
        protected WarrantyService       $warranties,
    ) {}

    // ── Read-side helpers ──────────────────────────────────────────────────

    public function listForProduct(int $productId, array $filters = []): LengthAwarePaginator
    {
        $q = ProductSerial::query()
            ->with(['branch:id,name', 'product:id,name,sku'])
            ->where('product_id', $productId);

        // Workspace scope first — Dhaka serials must never appear in the
        // Chattogram Inventory drawer. Explicit branch_id filter still
        // works as a narrowing drill-down (e.g. for cross-branch reports
        // launched from Main Branch).
        $q = app(BranchContextService::class)->scopeQuery($q);

        if (!empty($filters['status']))     $q->where('status', $filters['status']);
        if (!empty($filters['branch_id']))  $q->where('branch_id', $filters['branch_id']);
        if (!empty($filters['search']))     $q->where('serial_number', 'like', '%'.$filters['search'].'%');

        return $q->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    public function availableForSale(int $productId, ?int $branchId = null): Collection
    {
        return ProductSerial::query()
            ->where('product_id', $productId)
            ->inStock()
            ->forBranch($branchId)
            ->select(['id', 'serial_number', 'branch_id', 'warranty_expiry_date'])
            ->orderBy('serial_number')
            ->limit(500)                 // safety cap — UI uses virtual scroll for the rest
            ->get();
    }

    public function ownedByCustomer(int $customerId): Collection
    {
        return ProductSerial::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [ProductSerial::STATUS_SOLD, ProductSerial::STATUS_SALES_RETURNED])
            ->with(['product:id,name,sku'])
            ->orderByDesc('sale_date')
            ->get();
    }

    /** Sellable count of a serialized product — used by inventory dashboards. */
    public function inStockCount(int $productId, ?int $branchId = null): int
    {
        return ProductSerial::query()
            ->where('product_id', $productId)
            ->inStock()
            ->forBranch($branchId)
            ->count();
    }

    // ── ATTACH (purchase) ──────────────────────────────────────────────────

    /**
     * Validate + persist a batch of serial numbers received against a
     * purchase line. Returns the freshly-created ProductSerial rows.
     *
     * $payload shape:
     *   [
     *     'product_id'         => 1,
     *     'purchase_id'        => 42,
     *     'purchase_item_id'   => 99,
     *     'supplier_id'        => 7,
     *     'branch_id'          => 1,
     *     'expected_quantity'  => 5,
     *     'purchase_date'      => '2026-06-05',
     *     'warranty_months'    => 12,                              // optional
     *     'serials'            => ['SN001', 'SN002', ...],         // exact count
     *   ]
     */
    public function attachSerialsToPurchase(array $payload): Collection
    {
        $this->assertSerializedProduct($payload['product_id']);

        $serials = collect($payload['serials'] ?? [])
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values();

        $this->assertCountMatchesQuantity($serials->count(), (int) $payload['expected_quantity']);
        $this->assertNoLocalDuplicates($serials);

        // ── IDEMPOTENT RETRY ────────────────────────────────────────────
        // If this purchase already has serials for this product, see if
        // they exactly match what we're being asked to insert. If yes,
        // return the existing rows as a no-op — the client retried after
        // a network blip. If they partially overlap, that's a bug and we
        // refuse to corrupt the state.
        if (!empty($payload['purchase_id'])) {
            $existing = ProductSerial::where('purchase_id', $payload['purchase_id'])
                ->where('product_id',  $payload['product_id'])
                ->orderBy('serial_number')
                ->get();

            if ($existing->isNotEmpty()) {
                $existingSet = $existing->pluck('serial_number')->sort()->values()->all();
                $incomingSet = $serials->sort()->values()->all();
                if ($existingSet === $incomingSet) {
                    return $existing;           // ← idempotent success
                }
                throw ValidationException::withMessages([
                    'serials' => [__('errors.serials.purchase_already_has_serials')],
                ]);
            }
        }

        $this->assertNoGlobalDuplicates($serials);

        $expiry = $this->warranties->calculateExpiryDate(
            $payload['purchase_date'] ?? null,
            $payload['warranty_months'] ?? null,
        );

        return DB::transaction(function () use ($serials, $payload, $expiry) {
            $created = collect();
            $userId  = Auth::id();

            foreach ($serials as $number) {
                $serial = ProductSerial::create([
                    'product_id'           => $payload['product_id'],
                    'branch_id'            => $payload['branch_id'] ?? null,
                    'serial_number'        => $number,
                    'purchase_id'          => $payload['purchase_id'] ?? null,
                    'purchase_item_id'     => $payload['purchase_item_id'] ?? null,
                    'supplier_id'          => $payload['supplier_id'] ?? null,
                    'status'               => ProductSerial::STATUS_IN_STOCK,
                    'purchase_date'        => $payload['purchase_date'] ?? null,
                    'warranty_months'      => $payload['warranty_months'] ?? null,
                    'warranty_expiry_date' => $expiry,
                    'created_by'           => $userId,
                    'updated_by'           => $userId,
                ]);

                // Audit row — even on first creation, the purchase movement
                // is logged so the device timeline starts at "received".
                $this->movements->recordPurchase(
                    $serial,
                    refType: \App\Modules\Purchase\Models\Purchase::class,
                    refId:   (int) ($payload['purchase_id'] ?? 0),
                    toBranchId: $payload['branch_id'] ?? null,
                );

                $created->push($serial);
            }

            return $created;
        });
    }

    // ── PICK (sale) ────────────────────────────────────────────────────────

    /**
     * Move a set of serials from in_stock to sold for a specific sale line.
     * Returns the affected ProductSerial rows.
     */
    public function attachSerialsToSale(array $payload): Collection
    {
        $this->assertSerializedProduct($payload['product_id']);

        $serialIds = collect($payload['serial_ids'] ?? [])->filter()->values();
        if ($serialIds->isEmpty()) {
            throw ValidationException::withMessages([
                'serial_ids' => [__('errors.serials.no_serials_selected')],
            ]);
        }

        // ── IDEMPOTENT RETRY ────────────────────────────────────────────
        // If every requested serial is already marked sold on THIS sale,
        // the client is retrying after a network blip — return the rows
        // as-is. Anything else (mixed statuses, wrong sale_id) goes
        // through normal validation below.
        $alreadySold = ProductSerial::whereIn('id', $serialIds)
            ->where('product_id', $payload['product_id'])
            ->where('status', ProductSerial::STATUS_SOLD)
            ->where('sale_id', (int) $payload['sale_id'])
            ->get();
        if ($alreadySold->count() === $serialIds->count()) {
            return $alreadySold;            // ← idempotent success
        }

        $serials = ProductSerial::query()
            ->whereIn('id', $serialIds)
            ->where('product_id', $payload['product_id'])
            ->lockForUpdate()
            ->get();

        $this->assertAllFoundAndAvailable($serials, $serialIds->count(), $payload['branch_id'] ?? null);

        return DB::transaction(function () use ($serials, $payload) {
            foreach ($serials as $serial) {
                $this->movements->recordSale(
                    $serial,
                    refType:     \App\Modules\Sales\Models\Sale::class,
                    refId:       (int) $payload['sale_id'],
                    customerId:  $payload['customer_id'] ?? null,
                    saleItemId:  $payload['sale_item_id'] ?? null,
                );
            }
            return $serials;
        });
    }

    // ── RETURN (purchase + sales) ─────────────────────────────────────────

    public function returnToSupplier(array $payload): Collection
    {
        $serials = ProductSerial::whereIn('id', $payload['serial_ids'])
            ->where('product_id', $payload['product_id'])
            ->lockForUpdate()
            ->get();

        return DB::transaction(function () use ($serials, $payload) {
            foreach ($serials as $serial) {
                if ($serial->status !== ProductSerial::STATUS_IN_STOCK) {
                    throw ValidationException::withMessages([
                        'serial_ids' => [__('errors.serials.not_in_stock', ['sn' => $serial->serial_number])],
                    ]);
                }
                $this->movements->recordPurchaseReturn(
                    $serial,
                    refType: \App\Modules\Purchase\Models\PurchaseReturn::class,
                    refId:   (int) $payload['purchase_return_id'],
                );
            }
            return $serials;
        });
    }

    public function returnFromCustomer(array $payload): Collection
    {
        $serials = ProductSerial::whereIn('id', $payload['serial_ids'])
            ->where('product_id', $payload['product_id'])
            ->lockForUpdate()
            ->get();

        return DB::transaction(function () use ($serials, $payload) {
            foreach ($serials as $serial) {
                // Must have originally been sold on the originating sale.
                if ($serial->status !== ProductSerial::STATUS_SOLD) {
                    throw ValidationException::withMessages([
                        'serial_ids' => [__('errors.serials.not_sold', ['sn' => $serial->serial_number])],
                    ]);
                }
                if ($serial->sale_id !== (int) $payload['sale_id']) {
                    throw ValidationException::withMessages([
                        'serial_ids' => [__('errors.serials.not_on_sale', ['sn' => $serial->serial_number])],
                    ]);
                }
                $this->movements->recordSalesReturn(
                    $serial,
                    refType:     \App\Modules\Sales\Models\SaleReturn::class,
                    refId:       (int) $payload['sales_return_id'],
                    resellable:  (bool) ($payload['resellable'] ?? true),
                );
            }
            return $serials;
        });
    }

    // ── Guards ─────────────────────────────────────────────────────────────

    protected function assertSerializedProduct(int $productId): void
    {
        $product = Product::query()->select(['id', 'is_serialized'])->find($productId);
        if (!$product || !$product->is_serialized) {
            throw ValidationException::withMessages([
                'product_id' => [__('errors.serials.product_not_serialized')],
            ]);
        }
    }

    protected function assertCountMatchesQuantity(int $serialCount, int $expected): void
    {
        if ($serialCount !== $expected) {
            throw ValidationException::withMessages([
                'serials' => [__('errors.serials.count_mismatch', [
                    'have'   => $serialCount,
                    'expect' => $expected,
                ])],
            ]);
        }
    }

    protected function assertNoLocalDuplicates(Collection $serials): void
    {
        if ($serials->count() !== $serials->unique()->count()) {
            throw ValidationException::withMessages([
                'serials' => [__('errors.serials.duplicate_in_batch')],
            ]);
        }
    }

    protected function assertNoGlobalDuplicates(Collection $serials): void
    {
        $clash = ProductSerial::whereIn('serial_number', $serials)
            ->pluck('serial_number')
            ->first();
        if ($clash) {
            // Phase Y Round 2C — fire the "Duplicate Serial Attempt"
            // security alert before we throw the validation error.
            // Notification dispatch is best-effort so an outage in the
            // notification module never blocks the legitimate validation
            // failure from reaching the caller.
            try {
                app(\App\Modules\NotificationCenter\Services\SmartNotificationService::class)->push([
                    'category'         => 'security',
                    'code'             => 'serials.duplicate_attempt',
                    'severity'         => 'danger',
                    'urgency'          => 80,
                    'audience_role'    => 'admin',
                    'title'            => __('notifications.serials.duplicate.title'),
                    'message'          => __('notifications.serials.duplicate.message', ['sn' => $clash]),
                    'dedupe_key'       => 'serials.duplicate_attempt:'.$clash,
                    'cooldown_minutes' => 30,
                    'meta'             => ['serial_number' => $clash],
                ]);
            } catch (\Throwable $e) { /* never block the validation throw */ }

            throw ValidationException::withMessages([
                'serials' => [__('errors.serials.duplicate_in_system', ['sn' => $clash])],
            ]);
        }
    }

    protected function assertAllFoundAndAvailable(Collection $serials, int $expectedCount, ?int $branchId): void
    {
        if ($serials->count() !== $expectedCount) {
            throw ValidationException::withMessages([
                'serial_ids' => [__('errors.serials.unknown_serial')],
            ]);
        }
        foreach ($serials as $serial) {
            if ($serial->status !== ProductSerial::STATUS_IN_STOCK) {
                throw ValidationException::withMessages([
                    'serial_ids' => [__('errors.serials.not_in_stock', ['sn' => $serial->serial_number])],
                ]);
            }
            if ($branchId && $serial->branch_id && $serial->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'serial_ids' => [__('errors.serials.wrong_branch', ['sn' => $serial->serial_number])],
                ]);
            }
        }
    }
}
