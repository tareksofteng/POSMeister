<?php

namespace App\Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Unit;
use App\Modules\Product\Requests\StoreUnitRequest;
use App\Modules\Product\Requests\UpdateUnitRequest;
use App\Modules\Product\Resources\UnitResource;
use App\Modules\Product\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(private UnitService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return UnitResource::collection($this->service->all());
    }

    public function all(): JsonResponse
    {
        return response()->json($this->service->all()->values());
    }

    public function store(StoreUnitRequest $request): UnitResource
    {
        return new UnitResource($this->service->store($request->validated()));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        return new UnitResource($this->service->update($unit, $request->validated()));
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->service->delete($unit);
        return response()->json(null, 204);
    }
}
