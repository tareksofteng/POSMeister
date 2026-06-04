<?php

namespace App\Modules\Serials\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Serials\Models\ProductSerial;
use App\Modules\Serials\Requests\AttachPurchaseSerialsRequest;
use App\Modules\Serials\Requests\AttachSaleSerialsRequest;
use App\Modules\Serials\Resources\ProductSerialResource;
use App\Modules\Serials\Services\SerialTrackingService;
use App\Modules\Serials\Services\WarrantyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 |--------------------------------------------------------------------------
 | SerialController — thin HTTP face for the Serials module
 |--------------------------------------------------------------------------
 |
 | Controllers stay deliberately thin: validate, delegate, format the
 | response. All business rules + persistence live in
 | SerialTrackingService and SerialMovementService.
 */
class SerialController extends Controller
{
    public function __construct(
        protected SerialTrackingService $tracking,
        protected WarrantyService       $warranties,
    ) {}

    /** GET /api/products/{product}/serials */
    public function indexForProduct(int $product, Request $request): JsonResponse
    {
        $page = $this->tracking->listForProduct($product, [
            'status'    => $request->query('status'),
            'branch_id' => $request->query('branch_id'),
            'search'    => $request->query('q'),
            'per_page'  => $request->integer('per_page', 25),
        ]);

        return response()->json([
            'data' => ProductSerialResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /** GET /api/products/{product}/serials/available?branch_id= */
    public function availableForSale(int $product, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->tracking->availableForSale($product, $request->integer('branch_id')),
        ]);
    }

    /** GET /api/customers/{customer}/owned-devices */
    public function ownedByCustomer(int $customer): JsonResponse
    {
        return response()->json([
            'data' => ProductSerialResource::collection(
                $this->tracking->ownedByCustomer($customer)
            ),
        ]);
    }

    /** GET /api/products/{product}/serials/in-stock-count?branch_id= */
    public function inStockCount(int $product, Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'product_id' => $product,
                'count'      => $this->tracking->inStockCount($product, $request->integer('branch_id')),
            ],
        ]);
    }

    /** POST /api/serials/attach-purchase */
    public function attachToPurchase(AttachPurchaseSerialsRequest $request): JsonResponse
    {
        $created = $this->tracking->attachSerialsToPurchase($request->validated());
        return response()->json([
            'data' => ProductSerialResource::collection($created),
        ], 201);
    }

    /** POST /api/serials/attach-sale */
    public function attachToSale(AttachSaleSerialsRequest $request): JsonResponse
    {
        $affected = $this->tracking->attachSerialsToSale($request->validated());
        return response()->json([
            'data' => ProductSerialResource::collection($affected),
        ]);
    }

    /** GET /api/serials/{serial} — full detail incl. movement timeline */
    public function show(ProductSerial $serial): JsonResponse
    {
        $serial->load(['product:id,name,sku', 'branch:id,name', 'movements']);
        return response()->json([
            'data' => (new ProductSerialResource($serial))->resolve(),
            'timeline' => $serial->movements->map(fn ($m) => [
                'id'             => $m->id,
                'movement_type'  => $m->movement_type,
                'reference_type' => $m->reference_type,
                'reference_id'   => $m->reference_id,
                'from_branch_id' => $m->from_branch_id,
                'to_branch_id'   => $m->to_branch_id,
                'remarks'        => $m->remarks,
                'created_at'     => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** GET /api/serials/warranty-expiring?days=30 */
    public function warrantyExpiringSoon(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        return response()->json([
            'data' => $this->warranties->expiringSoon($days, $request->integer('branch_id'))
                ->map(fn ($s) => [
                    'id'                   => $s->id,
                    'serial_number'        => $s->serial_number,
                    'product_id'           => $s->product_id,
                    'product_name'         => $s->product?->name,
                    'branch_name'          => $s->branch?->name,
                    'warranty_expiry_date' => optional($s->warranty_expiry_date)->toDateString(),
                    'remaining_days'       => $s->warrantyRemainingDays(),
                    'status'               => $s->status,
                ])
                ->values(),
        ]);
    }
}
