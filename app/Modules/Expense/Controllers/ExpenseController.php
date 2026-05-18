<?php

namespace App\Modules\Expense\Controllers;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Requests\StoreExpenseRequest;
use App\Modules\Expense\Resources\ExpenseResource;
use App\Modules\Expense\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseResource::collection(
            $this->service->paginate($request->only([
                'search', 'expense_category_id', 'branch_id',
                'status', 'payment_method', 'from', 'to', 'per_page',
            ]))
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'                => 'nullable|date',
            'to'                  => 'nullable|date|after_or_equal:from',
            'expense_category_id' => 'nullable|integer|exists:expense_categories,id',
            'branch_id'           => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json(['data' => $this->service->summary($data)]);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($this->service->find($expense->id));
    }

    public function store(StoreExpenseRequest $request): ExpenseResource
    {
        $attachment = $request->file('attachment');
        $data       = $request->safe()->except('attachment');

        return new ExpenseResource($this->service->store($data, $attachment));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $attachment = $request->file('attachment');
        $data       = $request->safe()->except('attachment');

        return new ExpenseResource($this->service->update($expense, $data, $attachment));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->service->destroy($expense);
        return response()->json(null, 204);
    }
}
