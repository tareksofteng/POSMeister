<?php

namespace App\Modules\OMS\Controllers;

use App\Modules\OMS\Models\AutomationLog;
use App\Modules\OMS\Models\AutomationRule;
use App\Modules\OMS\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AutomationController extends Controller
{
    public function __construct(private readonly AutomationService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => AutomationRule::orderByDesc('id')->get()]);
    }

    public function show(AutomationRule $rule): JsonResponse
    {
        return response()->json([
            'data' => $rule->load(['logs' => fn($q) => $q->orderByDesc('triggered_at')->limit(50)]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $rule = AutomationRule::create($data);
        return response()->json(['data' => $rule], 201);
    }

    public function update(AutomationRule $rule, Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($rule->id));
        $rule->update($data);
        return response()->json(['data' => $rule->fresh()]);
    }

    public function destroy(AutomationRule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(['data' => ['ok' => true]]);
    }

    public function run(AutomationRule $rule): JsonResponse
    {
        $log = $this->service->runRule($rule);
        return response()->json(['data' => $log]);
    }

    public function runAll(): JsonResponse
    {
        $logs = $this->service->runAllActive();
        return response()->json(['data' => $logs]);
    }

    public function logs(Request $request): JsonResponse
    {
        $q = AutomationLog::query()->with('rule:id,name,trigger')->orderByDesc('id');
        if ($status = $request->input('status'))  $q->where('status', $status);
        if ($ruleId = $request->input('rule_id')) $q->where('rule_id', $ruleId);
        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name'          => 'required|string|max:150',
            'trigger'       => 'required|string|max:64',
            'condition'     => 'nullable|array',
            'action_type'   => 'required|in:notify,reorder_suggest,task,risk_flag',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
            'branch_id'     => 'nullable|integer|exists:branches,id',
        ];
    }
}
