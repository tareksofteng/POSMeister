<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically scopes all Eloquent queries to the authenticated user's branch.
 *
 * Rules:
 *  - Users with branch_id set  → only see data for their branch
 *  - Admin with branch_id null → see ALL branches (or specific via BranchScopeMiddleware)
 *  - Admin passing ?branch_id= → see that specific branch (set by BranchScopeMiddleware)
 *
 * Usage: Add `use BranchScoped;` to any model with a `branch_id` column.
 */
trait BranchScoped
{
    protected static function bootBranchScoped(): void
    {
        // ── Query scope ────────────────────────────────────────────────────
        static::addGlobalScope('branch', function (Builder $builder) {
            $user = auth()->user();

            if (! $user) {
                return; // Console / testing — no filter
            }

            // Admin can override via BranchScopeMiddleware (sets pos.activeBranchId)
            $activeBranchId = app()->bound('pos.activeBranchId')
                ? app('pos.activeBranchId')
                : $user->branch_id;

            if ($activeBranchId !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    $activeBranchId
                );
            }
        });

        // ── Auto-set branch_id on create ───────────────────────────────────
        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $activeBranchId = app()->bound('pos.activeBranchId')
                    ? app('pos.activeBranchId')
                    : auth()->user()?->branch_id;

                if ($activeBranchId) {
                    $model->branch_id = $activeBranchId;
                }
            }
        });
    }

    // ── Escape hatch ───────────────────────────────────────────────────────

    /**
     * Bypass branch scope — use in admin-only report queries.
     *
     * Usage: Product::allBranches()->where(...)->get()
     */
    public function scopeAllBranches(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
