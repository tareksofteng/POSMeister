<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict access by user role.
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,manager')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Access denied. You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
