<?php

namespace App\Http\Middleware;

use App\Modules\Branch\Services\BranchContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 |--------------------------------------------------------------------------
 | CurrentBranchMiddleware
 |--------------------------------------------------------------------------
 |
 | Sits BEFORE BranchScopeMiddleware in the api group. Reads the active
 | branch from (in this priority order):
 |
 |   1. X-Branch-Id request header (sent by the SPA on every API call)
 |   2. ?branch_id= query string  (legacy admin override path; still works)
 |   3. session('current_branch_id') (server-side fallback)
 |   4. auth()->user()->branch_id (sensible default — user's home branch)
 |
 | The resolved id (or null) is pushed onto the app container under the
 | existing key `pos.activeBranchId` AND mirrored into the request
 | attribute `current_branch_id` so any downstream code can read it via
 | `$request->attributes->get('current_branch_id')`.
 |
 | Unauthorized branch ids are rejected with 403 so manipulated frontends
 | can't sneak into a branch the user doesn't own.
 */
class CurrentBranchMiddleware
{
    public function __construct(protected BranchContextService $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $branchId = $this->resolveBranchId($request, $user);

        // Set via the service so the ACL check + audit log fire only
        // when this is a deliberate switch (not for every request).
        try {
            // Skip audit on per-request middleware passes — set() audits
            // only when called explicitly from the switch endpoint.
            $this->context->set($branchId, audit: false);
        } catch (\Throwable $e) {
            // Unauthorised branch id → wipe to user's home branch and
            // tell the SPA via 403 so it can show a toast.
            return response()->json([
                'message'        => 'You are not allowed to access this branch.',
                'allowed_branch' => $user->branch_id,
            ], 403);
        }

        // Mirror onto the request attribute for downstream consumers.
        $request->attributes->set('current_branch_id', $branchId);

        return $next($request);
    }

    /**
     * Decide which branch id should be active for this request.
     */
    protected function resolveBranchId(Request $request, $user): ?int
    {
        // 1. Explicit header from the SPA — primary source.
        $header = $request->header('X-Branch-Id');
        if ($header !== null && is_numeric($header)) {
            $id = (int) $header;
            return $id > 0 ? $id : null;
        }

        // 2. Legacy ?branch_id= query string (admin override path).
        if ($request->filled('branch_id') && is_numeric($request->query('branch_id'))) {
            return (int) $request->query('branch_id');
        }

        // 3. Server-side session.
        if (session()->has('current_branch_id')) {
            $id = session('current_branch_id');
            return is_numeric($id) ? (int) $id : null;
        }

        // 4. Fallback to the user's own branch.
        return $user->branch_id ? (int) $user->branch_id : null;
    }
}
