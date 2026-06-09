<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Models\NotificationRule;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | NotificationRuleEngine — Phase AB Round 3
 |--------------------------------------------------------------------------
 |
 | Consulted by SmartNotificationService::push() to overlay any admin-
 | configured overrides onto the detector's hand-rolled defaults BEFORE
 | the dedupe / cooldown / insert path runs.
 |
 | Two public methods:
 |
 |   shouldSuppress($payload)
 |     Returns true when the admin has disabled the code, the rule's
 |     branch filter rejects the payload's branch_id, or the alert's
 |     severity is below the rule's min_severity floor. Returning true
 |     means "drop the alert silently" — no row inserted, no row updated.
 |
 |   applyOverrides($payload)
 |     Returns a new payload array with the admin's overrides merged in.
 |     Override precedence:
 |       1. Non-null rule column        — admin's explicit choice
 |       2. Detector's payload          — sensible default
 |     Count-based escalation: if the detector emits meta.count or
 |     meta.hits, the engine consults warning/danger/critical_threshold
 |     and may PROMOTE or DEMOTE severity accordingly.
 |
 | Rule lookup is cached in-process for the lifetime of the request, so
 | every detector emitting 8 notifications doesn't hammer the DB. The
 | rules table is tiny (typically <100 rows) so we just load it once.
 |
 | The engine degrades gracefully:
 |   - notification_rules table missing  → every call is a no-op
 |   - no rule for the code              → returns payload unchanged
 |   - rule with all NULL overrides      → returns payload unchanged
 */
class NotificationRuleEngine
{
    private static ?array $cache = null;

    /**
     * Returns true if the rule for this payload's code says to silence it.
     */
    public function shouldSuppress(array $payload): bool
    {
        $rule = $this->ruleFor($payload['code'] ?? '');
        if (!$rule) return false;

        if (!$rule->enabled) return true;
        if (!$rule->matchesBranch($payload['branch_id'] ?? null)) return false;   // not in scope → use detector defaults

        // Compute the post-override severity to see if it survives the
        // min_severity floor. This matches what applyOverrides() will
        // do — we never want to suppress an alert that would have been
        // escalated up by the threshold rules.
        $effective = $this->computeSeverity($payload, $rule);
        if ($rule->min_severity && $this->rank($effective) < $this->rank($rule->min_severity)) {
            return true;
        }
        return false;
    }

    /**
     * Returns the payload with any non-null rule fields merged in.
     */
    public function applyOverrides(array $payload): array
    {
        $rule = $this->ruleFor($payload['code'] ?? '');
        if (!$rule || !$rule->matchesBranch($payload['branch_id'] ?? null)) {
            return $payload;
        }

        if ($rule->cooldown_minutes !== null) {
            $payload['cooldown_minutes'] = (int) $rule->cooldown_minutes;
        }
        if ($rule->audience_role !== null) {
            $payload['audience_role'] = $rule->audience_role;
        }

        $payload['severity'] = $this->computeSeverity($payload, $rule);
        return $payload;
    }

    /**
     * Severity decision tree:
     *   1. Detector's severity is the seed.
     *   2. If meta.count or meta.hits is set AND any threshold field is
     *      set on the rule, the engine PROMOTES (count ≥ threshold) the
     *      severity up to the matching tier. Counts can only promote,
     *      never demote — the detector is allowed to start louder than
     *      the rule for non-count signals (e.g. journal_imbalance is
     *      always critical regardless of count).
     *   3. max_severity caps the result.
     *   4. min_severity is enforced separately by shouldSuppress().
     */
    private function computeSeverity(array $payload, NotificationRule $rule): string
    {
        $sev = $payload['severity'] ?? 'info';
        $count = $payload['meta']['count']
              ?? $payload['meta']['hits']
              ?? null;

        if ($count !== null) {
            if ($rule->critical_threshold !== null && $count >= $rule->critical_threshold) {
                $sev = $this->maxSeverity($sev, 'critical');
            }
            if ($rule->danger_threshold !== null && $count >= $rule->danger_threshold) {
                $sev = $this->maxSeverity($sev, 'danger');
            }
            if ($rule->warning_threshold !== null && $count >= $rule->warning_threshold) {
                $sev = $this->maxSeverity($sev, 'warning');
            }
        }

        if ($rule->max_severity && $this->rank($sev) > $this->rank($rule->max_severity)) {
            $sev = $rule->max_severity;
        }
        return $sev;
    }

    private function maxSeverity(string $a, string $b): string
    {
        return $this->rank($a) >= $this->rank($b) ? $a : $b;
    }

    private function rank(string $s): int
    {
        return [
            'info'     => 1,
            'success'  => 1,
            'warning'  => 2,
            'danger'   => 3,
            'critical' => 4,
        ][$s] ?? 1;
    }

    /**
     * In-request cache — typically the cron sweep emits dozens of
     * notifications across 9 detectors; one rule lookup per code from
     * disk would be wasteful. Loading the entire (small) table once
     * and indexing in PHP is faster than 50 round-trips.
     */
    private function ruleFor(string $code): ?NotificationRule
    {
        if (!$code) return null;
        if (self::$cache === null) {
            self::$cache = $this->loadCache();
        }
        return self::$cache[$code] ?? null;
    }

    private function loadCache(): array
    {
        if (!Schema::hasTable('notification_rules')) return [];
        return NotificationRule::query()
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * Invalidate the in-process cache. Called by the rule CRUD endpoints
     * after a save so the next push() reflects the change immediately
     * (otherwise the scheduler thread would keep its stale snapshot
     * until its next boot).
     */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
