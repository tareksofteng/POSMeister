<?php

namespace App\Modules\UserManagement\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return User::with('branch:id,code,name')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%");
                })
            )
            ->when(
                $filters['branch_id'] ?? null,
                fn ($q, $bid) => $q->where('branch_id', $bid)
            )
            ->when(
                $filters['role'] ?? null,
                fn ($q, $role) => $q->where('role', $role)
            )
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->fresh('branch');
    }

    public function toggleStatus(User $user): User
    {
        // Prevent admin from deactivating their own account
        if ($user->id === auth()->id()) {
            throw new \RuntimeException('You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        if ($user->id === auth()->id()) {
            throw new \RuntimeException('You cannot delete your own account.');
        }

        // Revoke all tokens before deletion
        $user->tokens()->delete();
        $user->delete();
    }
}
