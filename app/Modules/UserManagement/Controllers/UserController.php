<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\UserManagement\Requests\StoreUserRequest;
use App\Modules\UserManagement\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Resources\UserResource;
use App\Modules\UserManagement\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    /**
     * GET /api/users
     * Query params: search, branch_id, role, is_active, per_page
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = $this->service->paginate($request->all());

        return UserResource::collection($users);
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return (new UserResource($user->load('branch')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('branch'));
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->service->update($user, $request->validated());

        return new UserResource($user);
    }

    /**
     * PUT /api/users/{user}/status — toggle active/inactive
     */
    public function toggleStatus(User $user): JsonResponse
    {
        try {
            $user = $this->service->toggleStatus($user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'   => $user->is_active ? 'User activated.' : 'User deactivated.',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $this->service->delete($user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
