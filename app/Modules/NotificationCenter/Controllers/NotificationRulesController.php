<?php

namespace App\Modules\NotificationCenter\Controllers;

use App\Modules\NotificationCenter\Models\NotificationRule;
use App\Modules\NotificationCenter\Models\SmartNotification;
use App\Modules\NotificationCenter\Services\NotificationRuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/*
 |--------------------------------------------------------------------------
 | NotificationRulesController — Phase AB Round 3
 |--------------------------------------------------------------------------
 |
 | Thin admin-only CRUD for notification_rules. Every mutating endpoint
 | flushes NotificationRuleEngine's in-process cache so the next push()
 | (e.g. by the cron-scheduled detector) sees the change without a
 | worker restart.
 |
 | Routes (admin-only via the route group middleware):
 |   GET    /api/notifications/rules           — list every rule
 |   GET    /api/notifications/rules/codes     — discoverable code list
 |   POST   /api/notifications/rules           — upsert a rule
 |   PUT    /api/notifications/rules/{rule}    — update a rule
 |   DELETE /api/notifications/rules/{rule}    — drop a rule (reset to detector default)
 */
class NotificationRulesController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = NotificationRule::query()
            ->orderBy('code')
            ->get();
        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/notifications/rules/codes
     *
     * Returns every distinct notification code the system has emitted in
     * the past 90 days. Lets the admin "Add rule" UI offer a typeahead
     * instead of forcing them to remember the exact code. Includes the
     * matching latest title/severity/category for context.
     */
    public function codes(): JsonResponse
    {
        $rows = SmartNotification::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('code, category, MAX(severity) as last_severity, MAX(title) as last_title, COUNT(*) as occurrences')
            ->groupBy('code', 'category')
            ->orderBy('code')
            ->get();

        // Codes that already have a rule are flagged so the UI can grey
        // them out in the "Add" picker.
        $configured = NotificationRule::query()->pluck('code')->all();

        return response()->json(['data' => [
            'codes'      => $rows,
            'configured' => $configured,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $rule = NotificationRule::query()->updateOrCreate(
            ['code' => $data['code']],
            array_merge($data, [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]),
        );
        NotificationRuleEngine::flush();

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, NotificationRule $rule): JsonResponse
    {
        $data = $this->validatePayload($request, $rule->id);
        $rule->update(array_merge($data, [
            'updated_by' => $request->user()->id,
        ]));
        NotificationRuleEngine::flush();

        return response()->json(['data' => $rule->fresh()]);
    }

    public function destroy(NotificationRule $rule): JsonResponse
    {
        $rule->delete();
        NotificationRuleEngine::flush();
        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Shared validator — the only difference between create and update
     * is the uniqueness rule on `code`. update() passes the current
     * row's id so the rule ignores it during the unique check.
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:notification_rules,code';
        if ($ignoreId) $uniqueRule .= ',' . $ignoreId;

        return $request->validate([
            'code'               => ['required', 'string', 'max:64', $uniqueRule],
            'enabled'            => ['boolean'],
            'cooldown_minutes'   => ['nullable', 'integer', 'min:1', 'max:43200'],   // ≤30 days
            'warning_threshold'  => ['nullable', 'integer', 'min:0'],
            'danger_threshold'   => ['nullable', 'integer', 'min:0'],
            'critical_threshold' => ['nullable', 'integer', 'min:0'],
            'min_severity'       => ['nullable', 'in:info,success,warning,danger,critical'],
            'max_severity'       => ['nullable', 'in:info,success,warning,danger,critical'],
            'audience_role'      => ['nullable', 'string', 'max:32'],
            'branch_ids'         => ['nullable', 'array'],
            'branch_ids.*'       => ['integer', 'exists:branches,id'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
