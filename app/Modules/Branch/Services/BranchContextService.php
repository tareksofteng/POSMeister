<?php

namespace App\Modules\Branch\Services;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Models\BranchSwitchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;

/*
 |--------------------------------------------------------------------------
 | BranchContextService — single source of truth for "which branch am I in?"
 |--------------------------------------------------------------------------
 |
 | The frontend sends X-Branch-Id on every authenticated request. The
 | CurrentBranchMiddleware reads it, validates the caller can access that
 | branch, and pins it onto the app container as `pos.activeBranchId`
 | (existing convention, see BranchScopeMiddleware).
 |
 | Services and controllers call BranchContextService::current() instead
 | of reading `auth()->user()->branch_id` directly. Returning NULL means
 | the user is in the "all branches" super workspace (admin on Main
 | Branch, viewing aggregated reports). All `scopeQuery()` callers
 | should treat NULL as "no scoping" and forward the query unchanged.
 |
 | The service is intentionally stateless and idempotent:
 |   current()        → reads from the container; never mutates
 |   set()            → mutates container + writes audit log + checks ACL
 |   isMainBranch()   → static-ish helper; configurable via config('branch.main_id')
 |   scopeQuery()     → centralised filter so we never write
 |                      "where('branch_id', auth()->user()->branch_id)"
 |                      inside a controller again
 */
class BranchContextService
{
    /** Container key used by the existing BranchScopeMiddleware. */
    public const CONTAINER_KEY = 'pos.activeBranchId';

    /** Default Main Branch id — overridable via config/branch.php. */
    public const DEFAULT_MAIN_BRANCH_ID = 1;

    // ── Reads ─────────────────────────────────────────────────────────────

    /**
     * Returns the active branch id, or NULL when the caller is operating
     * in the "all branches" super workspace (Main Branch view for admin).
     */
    public function current(): ?int
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            return null;
        }
        $value = app(self::CONTAINER_KEY);
        return $value ? (int) $value : null;
    }

    public function currentBranch(): ?Branch
    {
        $id = $this->current();
        if (! $id) return null;
        return Branch::find($id);
    }

    public function branchName(): ?string
    {
        return $this->currentBranch()?->name;
    }

    public function mainBranchId(): int
    {
        return (int) (config('branch.main_id') ?? self::DEFAULT_MAIN_BRANCH_ID);
    }

    /**
     * True when the active workspace is the Main Branch (i.e. admin in
     * the "super workspace" view that aggregates every branch). Used by
     * `scopeQuery()` to skip filtering entirely.
     */
    public function isMainBranch(): bool
    {
        $current = $this->current();
        return $current !== null && $current === $this->mainBranchId();
    }

    // ── Writes ────────────────────────────────────────────────────────────

    /**
     * Pin a branch onto the request lifecycle. Validates that the caller
     * is allowed to view that branch (admin = any, manager = assigned
     * branches, cashier = own only). Logs the switch to
     * branch_switch_logs for the compliance trail.
     *
     * @throws UnauthorizedException when the user can't access $branchId
     */
    public function set(?int $branchId, bool $audit = true): void
    {
        $user = Auth::user();
        if (! $user) {
            throw new UnauthorizedException('Authentication required to set branch context.');
        }

        if ($branchId !== null && ! $this->userCanAccess($user, $branchId)) {
            throw new UnauthorizedException('You are not allowed to switch to this branch.');
        }

        $previous = $this->current();

        // Treat "All branches" (null) as the absence of the binding rather
        // than a stored null. Some container internals (and downstream
        // consumers like the legacy `BranchScoped` trait) interpret the
        // bound-but-null state inconsistently, producing 500s like the one
        // we saw switching Dhaka → All. Forgetting is the cleanest signal.
        if ($branchId === null) {
            if (app()->bound(self::CONTAINER_KEY)) {
                app()->forgetInstance(self::CONTAINER_KEY);
            }
        } else {
            app()->instance(self::CONTAINER_KEY, $branchId);
        }

        if ($audit && $previous !== $branchId) {
            // Auditing must NEVER block the legitimate switch. A bad row
            // (FK constraint, log table missing in fresh deploys, etc.)
            // shouldn't surface a 500 to the cashier — the switch itself
            // is already done by the time we get here.
            try {
                $this->logSwitch($user, $previous, $branchId);
            } catch (\Throwable $e) {
                logger()->warning('branch.switch.audit_failed', [
                    'user_id' => $user->id,
                    'from'    => $previous,
                    'to'      => $branchId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    public function clear(): void
    {
        if (app()->bound(self::CONTAINER_KEY)) {
            app()->forgetInstance(self::CONTAINER_KEY);
        }
    }

    // ── Query helper ──────────────────────────────────────────────────────

    /**
     * Centralised branch filter. Callers feed a builder in and get a
     * builder out — never call `auth()->user()->branch_id` directly.
     *
     *   $q = Sale::query();
     *   $q = app(BranchContextService::class)->scopeQuery($q);
     *
     * Behaviour:
     *   - current() === null         → no filter (admin "all branches" view)
     *   - isMainBranch() === true    → no filter (Main = super workspace)
     *   - otherwise                  → where('branch_id', current())
     *
     * The $column argument lets callers point at a non-default column
     * (e.g. some join aliases use `branches.id`).
     */
    public function scopeQuery(Builder $query, string $column = 'branch_id'): Builder
    {
        if ($this->isMainBranch()) {
            return $query;
        }
        $current = $this->current();
        if ($current === null) {
            return $query;
        }
        return $query->where($column, $current);
    }

    // ── Available branches for the current user ───────────────────────────

    /**
     * The set of branches the current user is allowed to switch to.
     * Drives the dropdown options in BranchSwitcher.vue.
     */
    public function availableBranches(): Collection
    {
        $user = Auth::user();
        if (! $user) return Branch::query()->whereRaw('1=0')->get();

        $q = Branch::query()->where('is_active', true);

        if (! $user->isAdmin()) {
            // Manager + cashier: limited to their assigned branch only.
            // Multi-branch managers (when that feature lands) plug in
            // here by querying the pivot/permission table.
            $q->where('id', $user->branch_id);
        }

        return $q->orderBy('name')->get();
    }

    // ── ACL ───────────────────────────────────────────────────────────────

    protected function userCanAccess(User $user, int $branchId): bool
    {
        if ($user->isAdmin()) return true;
        return (int) $user->branch_id === (int) $branchId;
    }

    // ── Audit log ─────────────────────────────────────────────────────────

    protected function logSwitch(User $user, ?int $from, ?int $to): void
    {
        BranchSwitchLog::create([
            'user_id'        => $user->id,
            'from_branch_id' => $from,
            'to_branch_id'   => $to,
            'ip_address'     => request()?->ip(),
            'user_agent'     => substr((string) request()?->userAgent(), 0, 255),
            'created_at'     => now(),
        ]);
    }
}
