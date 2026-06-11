<?php

namespace App\Modules\NotificationCenter\Services;

use App\Modules\NotificationCenter\Channels\NotificationChannelInterface;
use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Support\Facades\Cache;

/*
 * The orchestrator the in-app notification service calls right after a
 * notification lands in the DB. It runs the alert through every
 * configured channel that:
 *
 *   (a) is ready (e.g. WebPushChannel returns false without VAPID keys)
 *   (b) the recipient hasn't opted out of for this priority
 *   (c) the priority-throttle hasn't already silenced
 *
 * Priority rules (matches the Phase AD spec):
 *
 *   CRITICAL   instant — even bypasses the user's mute window
 *   HIGH       max one per 30 minutes per code per audience
 *   MEDIUM     digest-eligible — UI surfaces it, channels skip
 *   LOW        digest-only — channels skip entirely
 *
 * Failures never surface to the caller — channels report via stats,
 * and the engine logs anything actionable. The in-app inbox is the
 * source of truth; channels are best-effort extras.
 */
class PushDispatcher
{
    public const SEND_INSTANT      = 'instant';
    public const SEND_THROTTLED    = 'throttled';
    public const SEND_DIGEST_ONLY  = 'digest';

    /** @var NotificationChannelInterface[] */
    private array $channels;

    public function __construct(array $channels = [])
    {
        $this->channels = $channels;
    }

    public function register(NotificationChannelInterface $channel): void
    {
        $this->channels[] = $channel;
    }

    /**
     * Run the notification through every channel. Idempotent — safe to
     * call twice for the same notification (channel-level dedupe is
     * handled inside the channel via the per-device endpoint).
     */
    public function dispatch(SmartNotification $notification): array
    {
        $strategy = $this->strategyFor($notification->severity);
        if ($strategy === self::SEND_DIGEST_ONLY) {
            return ['strategy' => $strategy, 'channels' => []];
        }

        if ($strategy === self::SEND_THROTTLED
            && $this->isThrottled($notification)
        ) {
            return ['strategy' => 'throttled_skipped', 'channels' => []];
        }

        $results = [];
        foreach ($this->channels as $channel) {
            if (!$channel->isReady()) continue;
            if (!$this->channelEnabledFor($channel, $notification)) continue;

            $results[$channel->code()] = $channel->deliver($notification);
        }

        if ($strategy === self::SEND_THROTTLED) {
            $this->stampThrottle($notification);
        }

        return ['strategy' => $strategy, 'channels' => $results];
    }

    // ── Strategy decision ──────────────────────────────────────────────

    private function strategyFor(string $severity): string
    {
        return match ($severity) {
            'critical'         => self::SEND_INSTANT,
            'danger'           => self::SEND_THROTTLED,
            'warning'          => self::SEND_DIGEST_ONLY,
            'info', 'success'  => self::SEND_DIGEST_ONLY,
            default            => self::SEND_DIGEST_ONLY,
        };
    }

    // ── Throttle (30-minute cooldown per code+audience for high) ────────

    private function throttleKey(SmartNotification $n): string
    {
        return sprintf(
            'push.throttle:%s:%s:%s',
            $n->code,
            $n->audience_role ?: ('u' . $n->audience_user_id),
            $n->branch_id ?? 'global',
        );
    }

    private function isThrottled(SmartNotification $n): bool
    {
        return Cache::has($this->throttleKey($n));
    }

    private function stampThrottle(SmartNotification $n): void
    {
        Cache::put($this->throttleKey($n), 1, now()->addMinutes(30));
    }

    // ── Per-user opt-outs ─────────────────────────────────────────────

    /**
     * The preferences row carries a `channels` JSON column:
     *
     *   { "web_push": { "critical": true, "high": true, "medium": false, "low": false } }
     *
     * When a user opts out of "high" Web Push, the channel still tries
     * for "critical" alerts. If the channel block is missing entirely we
     * assume defaults: critical + high enabled, lower disabled.
     */
    private function channelEnabledFor(NotificationChannelInterface $channel, SmartNotification $n): bool
    {
        if (!$n->audience_user_id) return true;     // role-broadcast: per-device caller handles opt-outs at send time

        $prefs = NotificationPreference::query()->where('user_id', $n->audience_user_id)->first();
        if (!$prefs) return true;
        $block = $prefs->channels[$channel->code()] ?? null;
        if (!$block) return true;

        $bucket = $this->priorityBucket($n->severity);
        return $block[$bucket] ?? true;
    }

    private function priorityBucket(string $severity): string
    {
        return match ($severity) {
            'critical'         => 'critical',
            'danger'           => 'high',
            'warning'          => 'medium',
            default            => 'low',
        };
    }
}
