<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Models\Budget;
use App\Modules\Finance\Requests\StoreBudgetRequest;
use App\Modules\Finance\Resources\BudgetResource;
use App\Modules\Finance\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return BudgetResource::collection(
            $this->service->paginate($request->only(['status', 'fiscal_year', 'branch_id', 'per_page']))
        );
    }

    public function show(Budget $budget): BudgetResource
    {
        return new BudgetResource($this->service->find($budget->id));
    }

    public function store(StoreBudgetRequest $request): BudgetResource
    {
        try {
            return new BudgetResource($this->service->store($request->validated()));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function update(StoreBudgetRequest $request, Budget $budget): BudgetResource
    {
        try {
            return new BudgetResource($this->service->update($budget, $request->validated()));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function setStatus(Request $request, Budget $budget): BudgetResource
    {
        $data = $request->validate(['status' => 'required|in:draft,active,archived']);
        try {
            return new BudgetResource($this->service->setStatus($budget, $data['status']));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function duplicate(Request $request, Budget $budget): BudgetResource
    {
        $data = $request->validate(['fiscal_year' => 'required|integer|min:2000|max:2100']);
        return new BudgetResource($this->service->duplicate($budget, $data['fiscal_year']));
    }

    public function destroy(Budget $budget): JsonResponse
    {
        try {
            $this->service->destroy($budget);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }
}
