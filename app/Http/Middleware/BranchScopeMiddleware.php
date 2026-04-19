<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the active branch context used by the BranchScoped trait.
 *
 * Normal users: their branch is set automatically from auth()->user()->branch_id.
 * Admin override: admin can pass ?branch_id=X to scope queries to a specific branch.
 *                 Without it, admin sees ALL branches.
 *
 * Future SaaS: this is also the hook for branch-switching in multi-tenant mode.
 */
class BranchScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Non-admin: always use their own branch — no override allowed
        if (! $user->isAdmin()) {
            if ($user->branch_id) {
                app()->instance('pos.activeBranchId', $user->branch_id);
            }
            return $next($request);
        }

        // Admin: can pass ?branch_id=X to scope to a specific branch
        if ($request->filled('branch_id') && is_numeric($request->branch_id)) {
            app()->instance('pos.activeBranchId', (int) $request->branch_id);
        }
        // If admin doesn't pass branch_id — no scope applied, they see everything

        return $next($request);
    }
}
