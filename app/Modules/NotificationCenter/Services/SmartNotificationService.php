<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Models\SmartNotification;
use App\Modules\NotificationCenter\Services\NotificationRuleEngine;
use Illuminate\Support\Carbon;

/**
 * Single entry point used by every detector to publish an alert.
 *
 *   $svc->push([
 *       'category'  => 'inventory',
 *       'code'      => 'inventory.low_stock',
 *       'severity'  => 'warning',
 *       'title'     => '8 products below reorder level',
 *       'message'   => 'Review and create a purchase order.',
 *       'audience_role' => 'admin',
 *       'dedupe_key'    => 'inventory.low_stock:branch-1',
 *       'cooldown_minutes' => 60,
 *       'actions'   => [['label' => 'Open inventory', 'route' => 'inventory-reorder']],
 *       'entity_type' => null,
 *       'entity_id'   => null,
 *   ]);
 *
 * Dedupe + cooldown:
 *   - Same (dedupe_key, audience_user_id, audience_role) inside cooldown
 *     window updates the existing row (bumps timestamp, severity, message)
 *     instead of creating a new one. Stops the bell icon from spamming.
 */
class SmartNotificationService
{
    public function __construct(
        private readonly ?NotificationRuleEngine $rules = null,
    ) {}

    public function push(array $payload): ?SmartNotification
    {
        $payload = array_merge([
            'severity'         => 'info',
            'urgency'          => 50,
            'cooldown_minutes' => 60,
            'audience_role'    => null,
            'audience_user_id' => null,
            'branch_id'        => null,
            'meta'             => null,
            'actions'          => null,
            'entity_type'      => null,
            'entity_id'        => null,
            'expires_at'       => null,
        ], $payload);

        // Phase AB Round 3 — admin-configurable rule overlay. Consulted
        // BEFORE user-pref filtering and the dedupe/cooldown lookup so a
        // disabled code or a sub-floor severity short-circuits cleanly.
        // The engine is optional (constructor-injected) so the existing
        // test harness that builds SmartNotificationService by hand
        // still works without wiring it up.
        if ($this->rules) {
            if ($this->rules->shouldSuppress($payload)) return null;
            $payload = $this->rules->applyOverrides($payload);
        }

        if ($this->shouldSkip($payload)) return null;

        $existing = SmartNotification::query()
            ->where('dedupe_key', $payload['dedupe_key'])
            ->where('audience_user_id', $payload['audience_user_id'])
            ->where('audience_role', $payload['audience_role'])
            ->first();

        if ($existing && $existing->cooldown_until && $existing->cooldown_until->isFuture()) {
            // Inside cooldown — refresh content + bump escalation if severity climbed
            $sevRank = ['info' => 1, 'success' => 1, 'warning' => 2, 'danger' => 3, 'critical' => 4];
            if (($sevRank[$payload['severity']] ?? 1) > ($sevRank[$existing->severity] ?? 1)) {
                $existing->severity = $payload['severity'];
                $existing->escalation_level += 1;
            }
            $existing->title   = $payload['title'];
            $existing->message = $payload['message'];
            if (!empty($payload['actions'])) $existing->actions = $payload['actions'];
            if (!empty($payload['meta']))    $existing->meta    = $payload['meta'];
            $existing->save();
            return $existing;
        }

        return SmartNotification::query()->updateOrCreate(
            [
                'dedupe_key'       => $payload['dedupe_key'],
                'audience_user_id' => $payload['audience_user_id'],
                'audience_role'    => $payload['audience_role'],
            ],
            [
                'category'         => $payload['category'],
                'code'             => $payload['code'],
                'severity'         => $payload['severity'],
                'urgency'          => (int) $payload['urgency'],
                'title'            => $payload['title'],
                'message'          => $payload['message'],
                'actions'          => $payload['actions'],
                'meta'             => $payload['meta'],
                'entity_type'      => $payload['entity_type'],
                'entity_id'        => $payload['entity_id'],
                'branch_id'        => $payload['branch_id'],
                'cooldown_until'   => now()->addMinutes((int) $payload['cooldown_minutes']),
                'expires_at'       => $payload['expires_at'],
                'archived_at'      => null,
                'read_at'          => null,
                'acked_at'         => null,
            ]
        );
    }

    /**
     * Respect per-user preferences when the alert targets a specific user.
     * Role-broadcast alerts are not filtered here (each user filters in their
     * own list query).
     */
    private function shouldSkip(array $p): bool
    {
        if (empty($p['audience_user_id'])) return false;
        $prefs = NotificationPreference::query()->where('user_id', $p['audience_user_id'])->first();
        if (!$prefs) return false;

        $muted = $prefs->muted_categories ?? [];
        if (in_array($p['category'], $muted, true)) return true;

        $rank = ['info' => 1, 'success' => 1, 'warning' => 2, 'danger' => 3, 'critical' => 4];
        if (($rank[$p['severity']] ?? 1) < ($rank[$prefs->min_severity] ?? 1)) return true;

        $qh = $prefs->quiet_hours ?? null;
        if (is_array($qh) && !empty($qh['from']) && !empty($qh['to'])) {
            $now = Carbon::now()->format('H:i');
            $from = $qh['from']; $to = $qh['to'];
            $inWindow = $from < $to
                ? ($now >= $from && $now < $to)
                : ($now >= $from || $now < $to);
            // critical alerts bypass quiet hours
            if ($inWindow && $p['severity'] !== 'critical') return true;
        }
        return false;
    }
}
