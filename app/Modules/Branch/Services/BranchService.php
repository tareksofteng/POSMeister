<?php

namespace App\Modules\Branch\Services;

use App\Modules\Branch\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Branch::withCount('users')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('code', 'like', "%{$s}%");
                })
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * All active branches for dropdowns — lightweight, no pagination.
     */
    public function allActive(): Collection
    {
        return Branch::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    public function create(array $data): Branch
    {
        return Branch::create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);
        return $branch->fresh();
    }

    public function delete(Branch $branch): void
    {
        // Prevent deletion if users are assigned
        if ($branch->users()->exists()) {
            throw new \RuntimeException(
                "Cannot delete branch \"{$branch->name}\" — it has active users assigned. "
                . "Reassign or deactivate users first."
            );
        }

        $branch->delete();
    }
}
