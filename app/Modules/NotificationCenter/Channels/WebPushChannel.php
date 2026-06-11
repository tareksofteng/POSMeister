<?php

namespace App\Modules\NotificationCenter\Channels;

use App\Modules\NotificationCenter\Models\PushSubscription;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Support\Facades\Log;

/*
 * Browser Web Push channel. Encrypts and signs the payload via
 * minishlink/web-push (RFC8030 / VAPID), then ships to each registered
 * endpoint for the target audience.
 *
 * The channel is OPTIONAL. If VAPID keys aren't set or the composer
 * package isn't installed, isReady() returns false and the dispatcher
 * skips push without breaking anything else. In-app notifications keep
 * working untouched.
 *
 * Recipient resolution:
 *   - audience_user_id set       → that user's active devices
 *   - audience_role  = 'admin'   → every active device for users with that role
 *   - branch-scoped notification → filtered to subscriptions whose
 *                                  branch_id matches OR is NULL
 *                                  (global notifications reach every device)
 */
class WebPushChannel implements NotificationChannelInterface
{
    public function code(): string
    {
        return 'web_push';
    }

    public function isReady(): bool
    {
        if (empty(config('push.public_key')) || empty(config('push.private_key'))) {
            return false;
        }
        // minishlink/web-push provides the encryption + VAPID signing.
        // Without it, the channel can still hand out the public key for
        // subscription, but it cannot SEND. We probe its presence here.
        return class_exists(\Minishlink\WebPush\WebPush::class);
    }

    public function deliver(SmartNotification $notification): array
    {
        $stats = ['attempted' => 0, 'delivered' => 0, 'failed' => 0, 'skipped' => 0];

        if (!$this->isReady()) {
            $stats['skipped'] = 1;
            return $stats;
        }

        $subs = $this->resolveSubscriptions($notification);
        if ($subs->isEmpty()) {
            $stats['skipped'] = 1;
            return $stats;
        }

        try {
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject'    => config('push.subject'),
                    'publicKey'  => config('push.public_key'),
                    'privateKey' => config('push.private_key'),
                ],
            ]);

            $payload = json_encode($this->buildPayload($notification));

            foreach ($subs as $sub) {
                $stats['attempted']++;
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create($sub->toWebPushPayload()),
                    $payload,
                );
            }

            // Drain the queue and handle each response. The library is
            // batch-friendly — one HTTP/2 round-trip per push service.
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $sub = $subs->firstWhere('endpoint', $endpoint);
                if (!$sub) continue;

                if ($report->isSuccess()) {
                    $stats['delivered']++;
                    $sub->markDelivered();
                    continue;
                }

                $stats['failed']++;
                $code  = method_exists($report, 'getResponse') && $report->getResponse()
                    ? $report->getResponse()->getStatusCode()
                    : 0;
                $reason = $report->getReason() ?: "HTTP {$code}";

                if (in_array($code, [404, 410], true)) {
                    // Permanent — the browser tells us the subscription
                    // is gone (user uninstalled / reset). Retire it.
                    $sub->markGone($reason);
                } else {
                    $sub->markTransientFailure($reason);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('web-push.deliver_failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            $stats['failed'] = count($subs);
        }

        return $stats;
    }

    /**
     * Build the JSON the Service Worker's `push` event handler reads.
     * Compact, action-rich, deep-link ready.
     */
    private function buildPayload(SmartNotification $n): array
    {
        $primary = is_array($n->actions ?? null)
            ? collect($n->actions)->first(fn ($a) => ($a['type'] ?? null) === 'primary') ?? collect($n->actions)->first()
            : null;

        return [
            'id'        => $n->id,
            'code'      => $n->code,
            'category'  => $n->category,
            'severity'  => $n->severity,
            'urgency'   => (int) $n->urgency,
            'branch_id' => $n->branch_id,
            'title'     => $n->title,
            'body'      => $n->message,
            'actions'   => collect($n->actions ?? [])->take(2)->map(fn ($a) => [
                'action' => $a['route']  ?? '',
                'title'  => $a['label']  ?? '',
            ])->all(),
            'click'     => $primary
                ? ['route' => $primary['route'] ?? null, 'params' => $primary['params'] ?? null]
                : null,
            'sent_at'   => now()->toIso8601String(),
        ];
    }

    /**
     * Pick the devices that should receive this alert. Branch-scoped
     * notifications reach devices subscribed to that branch OR with no
     * branch_id (admin-wide); company-wide notifications reach everyone.
     */
    private function resolveSubscriptions(SmartNotification $n)
    {
        $q = PushSubscription::query()->active();

        if ($n->audience_user_id) {
            $q->where('user_id', $n->audience_user_id);
        } elseif ($n->audience_role) {
            $q->whereHas('user', fn ($u) => $u->where('role', $n->audience_role));
        }

        if ($n->branch_id !== null) {
            $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $n->branch_id));
        }

        return $q->get();
    }
}
