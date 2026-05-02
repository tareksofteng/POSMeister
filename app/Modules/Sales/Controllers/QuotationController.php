<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Resources\QuotationResource;
use App\Modules\Sales\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $quotations = $this->service->paginate($request->only([
            'search', 'status', 'date_from', 'date_to', 'branch_id', 'per_page',
        ]));

        return QuotationResource::collection($quotations);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        return new QuotationResource(
            $this->service->find($quotation->id)
        );
    }

    public function store(Request $request): QuotationResource
    {
        $this->validateRequest($request);

        try {
            $quotation = $this->service->store($request->all());
            return new QuotationResource($quotation);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function update(Request $request, Quotation $quotation): QuotationResource
    {
        $this->validateRequest($request);

        try {
            $updated = $this->service->update($quotation, $request->all());
            return new QuotationResource($updated);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Quotation $quotation): QuotationResource
    {
        $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);

        try {
            $updated = $this->service->updateStatus($quotation, $request->string('status')->value());
            return new QuotationResource($updated);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        try {
            $this->service->delete($quotation);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateRequest(Request $request): void
    {
        $request->validate([
            'quotation_date'         => 'required|date',
            'valid_until'            => 'nullable|date|after_or_equal:quotation_date',
            'customer_id'            => 'nullable|integer|exists:customers,id',
            'customer_name'          => 'nullable|string|max:150',
            'customer_phone'         => 'nullable|string|max:30',
            'customer_email'         => 'nullable|email|max:150',
            'customer_address'       => 'nullable|string|max:255',
            'quotation_type'         => 'nullable|in:retail,wholesale',
            'discount_amount'        => 'nullable|numeric|min:0',
            'freight_amount'         => 'nullable|numeric|min:0',
            'terms'                  => 'nullable|string',
            'note'                   => 'nullable|string',
            'status'                 => 'nullable|in:draft,sent,accepted,rejected,expired',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'nullable|integer|exists:products,id',
            'items.*.description'    => 'nullable|string|max:255',
            'items.*.quantity'       => 'required|numeric|min:0.01',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.tax_rate'       => 'required|numeric|min:0|max:100',
            'items.*.is_service'     => 'nullable|boolean',
        ]);
    }
}
