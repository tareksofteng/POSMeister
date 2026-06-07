<?php

namespace App\Modules\Branch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

/*
 |--------------------------------------------------------------------------
 | BranchContextController — thin
 |--------------------------------------------------------------------------
 |
 | Three endpoints power the Topbar branch switcher:
 |
 |   GET  /api/branch-context/current   → who am I right now?
 |   GET  /api/branch-context/available → list of branches I can switch to
 |   POST /api/branch-context/switch    → audited switch with ACL check
 |
 | Persistence happens in two places:
 |   - Session (`current_branch_id`) so a page reload keeps the workspace.
 |   - Frontend localStorage so the PWA shell can restore offline.
 |
 | The middleware reads the X-Branch-Id header on every subsequent
 | request, so this endpoint only fires once per switch.
 */
class BranchContextController extends Controller
{
    public function __construct(protected BranchContextService $context) {}

    public function current(Request $request): JsonResponse
    {
        $branch = $this->context->currentBranch();

        return response()->json([
            'data' => [
                'branch_id'       => $this->context->current(),
                'branch_name'     => $branch?->name,
                'branch_code'     => $branch?->code,
                'is_main_branch'  => $this->context->isMainBranch(),
                'is_all_branches' => $this->context->current() === null,
                'main_branch_id'  => $this->context->mainBranchId(),
            ],
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $branches = $this->context->availableBranches();
        return response()->json([
            'data' => $branches->map(fn ($b) => [
                'id'        => $b->id,
                'code'      => $b->code,
                'name'      => $b->name,
                'is_active' => (bool) $b->is_active,
                'is_main'   => (int) $b->id === $this->context->mainBranchId(),
            ])->values(),
        ]);
    }

    public function switch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $newBranchId = $validated['branch_id'] ?? null;

        try {
            $this->context->set($newBranchId, audit: true);
        } catch (UnauthorizedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        // Persist server-side so a page reload keeps the same workspace.
        // Null = "All branches" super workspace → drop the session key
        // entirely so the next request's middleware falls back to the
        // user's home branch cleanly (instead of reading a null sentinel).
        if ($newBranchId === null) {
            session()->forget('current_branch_id');
        } else {
            session(['current_branch_id' => $newBranchId]);
        }

        return $this->current($request);
    }
}
