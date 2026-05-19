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
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpenseResource::collection(
            $this->service->paginate($request->only([
                'search', 'expense_category_id', 'branch_id',
                'status', 'payment_method', 'is_recurring',
                'from', 'to', 'per_page',
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
        try {
            $attachment = $request->file('attachment');
            $data       = $request->safe()->except('attachment');
            return new ExpenseResource($this->service->update($expense, $data, $attachment));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function destroy(Expense $expense): JsonResponse
    {
        try {
            $this->service->destroy($expense);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(null, 204);
    }

    public function approve(Request $request, Expense $expense): ExpenseResource
    {
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        try {
            return new ExpenseResource($this->service->approve($expense, $data['notes'] ?? null));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function reject(Request $request, Expense $expense): ExpenseResource
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        try {
            return new ExpenseResource($this->service->reject($expense, $data['reason']));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function markPaid(Request $request, Expense $expense): ExpenseResource
    {
        $data = $request->validate([
            'paid_at'         => 'nullable|date',
            'payment_method'  => 'nullable|in:cash,card,bank_transfer,cheque,other',
            'reference_no'    => 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);
        try {
            return new ExpenseResource($this->service->markPaid($expense, $data));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function reopen(Request $request, Expense $expense): ExpenseResource
    {
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        try {
            return new ExpenseResource($this->service->reopen($expense, $data['notes'] ?? null));
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function auditLog(Expense $expense): JsonResponse
    {
        return response()->json(['data' => $this->service->auditLog($expense)]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from'                => 'nullable|date',
            'to'                  => 'nullable|date|after_or_equal:from',
            'expense_category_id' => 'nullable|integer|exists:expense_categories,id',
            'status'              => 'nullable|in:pending,approved,paid,rejected',
            'branch_id'           => 'nullable|integer|exists:branches,id',
            'format'              => 'nullable|in:standard,datev',
        ]);

        return $this->service->exportCsv($data, $data['format'] ?? 'standard');
    }
}
