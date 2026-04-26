<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Resources\SaleReturnResource;
use App\Modules\Sales\Services\SaleReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class SaleReturnController extends Controller
{
    public function __construct(private readonly SaleReturnService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $returns = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'per_page',
        ]));

        return SaleReturnResource::collection($returns);
    }

    public function returnDetails(int $saleId): JsonResponse
    {
        try {
            return response()->json($this->service->getReturnDetails($saleId));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function store(Request $request): SaleReturnResource
    {
        $request->validate([
            'sale_id'               => 'required|integer|exists:sales,id',
            'return_date'           => 'required|date',
            'items'                 => 'required|array|min:1',
            'items.*.sale_item_id'  => 'required|integer|exists:sale_items,id',
            'items.*.product_id'    => 'required|integer|exists:products,id',
            'items.*.return_qty'    => 'required|numeric|min:0',
            'items.*.unit_price'    => 'required|numeric|min:0',
            'note'                  => 'nullable|string|max:500',
        ]);

        try {
            $return = $this->service->store($request->all());
            return new SaleReturnResource($return);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
