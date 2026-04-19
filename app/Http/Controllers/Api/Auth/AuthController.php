<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\RolePermission\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private RolePermissionService $permissions) {}

    /**
     * Authenticate and return a Sanctum token + user + permissions.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact your administrator.'],
            ]);
        }

        // One active session per user
        $user->tokens()->delete();

        $token = $user->createToken(
            name:      'pos-session',
            abilities: ['*'],
            expiresAt: now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'token'       => $token,
            'user'        => $this->formatUser($user),
            'permissions' => $this->permissions->getForRole($user->role),
        ]);
    }

    /**
     * Return current user + fresh permissions (for page refresh / re-sync).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user'        => $this->formatUser($user),
            'permissions' => $this->permissions->getForRole($user->role),
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'role'      => $user->role,
            'branch_id' => $user->branch_id,
        ];
    }
}
