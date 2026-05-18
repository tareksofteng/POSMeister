<?php

namespace App\Modules\Expense\Controllers;

use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Requests\StoreExpenseCategoryRequest;
use App\Modules\Expense\Resources\ExpenseCategoryResource;
use App\Modules\Expense\Services\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ExpenseCategoryResource::collection($this->service->all()),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->activeForDropdown(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = $this->service->store($request->validated());
        return response()->json(['data' => new ExpenseCategoryResource($category)], 201);
    }

    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $category): JsonResponse
    {
        $updated = $this->service->update($category, $request->validated());
        return response()->json(['data' => new ExpenseCategoryResource($updated)]);
    }

    public function toggleStatus(ExpenseCategory $category): JsonResponse
    {
        return response()->json(['data' => new ExpenseCategoryResource($this->service->toggleStatus($category))]);
    }

    public function destroy(ExpenseCategory $category): JsonResponse
    {
        try {
            $this->service->delete($category);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }
}
