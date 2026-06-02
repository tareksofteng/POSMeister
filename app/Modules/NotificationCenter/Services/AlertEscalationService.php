<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Models\SmartNotification;

/**
 * Bumps the severity of long-running, un-acked alerts.
 *
 *   warning  ─── 24h unacked ──▶  danger
 *   danger   ─── 48h unacked ──▶  critical
 *
 * Critical alerts cannot escalate further; they stay until acked.
 */
class AlertEscalationService
{
    private const RULES = [
        'warning'  => ['after_hours' => 24, 'to' => 'danger'],
        'danger'   => ['after_hours' => 48, 'to' => 'critical'],
    ];

    public function run(): int
    {
        $changed = 0;
        foreach (self::RULES as $from => $rule) {
            $threshold = now()->subHours($rule['after_hours']);
            $changed += SmartNotification::query()
                ->where('severity', $from)
                ->whereNull('acked_at')
                ->whereNull('archived_at')
                ->where('created_at', '<', $threshold)
                ->update([
                    'severity'         => $rule['to'],
                    'escalation_level' => \DB::raw('escalation_level + 1'),
                    'updated_at'       => now(),
                ]);
        }
        return $changed;
    }

    /**
     * Hard-delete archived notifications older than $keepDays.
     * Keeps the table compact without losing recent history.
     */
    public function pruneArchived(int $keepDays = 30): int
    {
        return SmartNotification::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays($keepDays))
            ->delete();
    }
}
