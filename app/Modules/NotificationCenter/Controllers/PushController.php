<?php

namespace App\Modules\NotificationCenter\Controllers;

use App\Modules\Branch\Services\BranchContextService;
use App\Modules\NotificationCenter\Models\NotificationClick;
use App\Modules\NotificationCenter\Models\PushSubscription;
use App\Modules\NotificationCenter\Models\SmartNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/*
 * Browser-side lifecycle endpoints for Web Push.
 *
 *   GET    /api/push/vapid-key            VAPID public key for the SW subscribe call
 *   POST   /api/push/subscribe            register a new device (idempotent on endpoint)
 *   POST   /api/push/unsubscribe          tear down a device on logout
 *   POST   /api/push/devices/{id}/rename  user-friendly label
 *   DELETE /api/push/devices/{id}         revoke a device the user no longer trusts
 *   GET    /api/push/devices              the user's own registered devices
 *   GET    /api/push/analytics            admin overview — reach + active devices
 */
class PushController extends Controller
{
    /** Public key — readable without auth so the SW can read it during install. */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'data' => [
                'public_key' => config('push.public_key'),
                'enabled'    => !empty(config('push.public_key')),
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'    => ['required', 'string', 'max:2000'],
            'p256dh_key'  => ['required', 'string', 'max:200'],
            'auth_token'  => ['required', 'string', 'max:80'],
            'browser'     => ['nullable', 'string', 'max:32'],
            'platform'    => ['nullable', 'string', 'max:32'],
            'device_type' => ['nullable', 'in:mobile,tablet,desktop'],
            'label'       => ['nullable', 'string', 'max:80'],
        ]);

        $ctx = app(BranchContextService::class);

        $sub = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            array_merge($data, [
                'user_id'      => $request->user()->id,
                'branch_id'    => $ctx->isMainBranch() ? null : $ctx->current(),
                'is_active'    => true,
                'last_seen_at' => now(),
                'failure_count' => 0,
                'last_failed_at' => null,
                'last_failure_reason' => null,
            ]),
        );

        return response()->json(['data' => $this->shape($sub)], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        if (!$endpoint) return response()->json(['data' => ['ok' => true]]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $endpoint)
            ->update(['is_active' => false]);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function devices(Request $request): JsonResponse
    {
        $rows = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderByDesc('last_seen_at')
            ->get();

        return response()->json(['data' => $rows->map(fn ($s) => $this->shape($s))->all()]);
    }

    public function rename(Request $request, PushSubscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate(['label' => ['required', 'string', 'max:80']]);
        $subscription->update($data);
        return response()->json(['data' => $this->shape($subscription)]);
    }

    public function destroy(Request $request, PushSubscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $subscription->update(['is_active' => false]);
        return response()->json(['data' => ['ok' => true]]);
    }

    public function analytics(): JsonResponse
    {
        $today = now()->startOfDay();
        $week  = now()->subWeek();

        // ── Click metrics (Phase AD Round 3) ─────────────────────────────
        // Notifications sent today = smart_notifications created today.
        // Click rate = unique-notification clicks / sent. We count
        // distinct notification_ids so 3 taps on the same alert don't
        // inflate the CTR.
        $sentToday   = SmartNotification::query()->where('created_at', '>=', $today)->count();
        $sentWeek    = SmartNotification::query()->where('created_at', '>=', $week)->count();

        $clicksToday = NotificationClick::query()
            ->where('clicked_at', '>=', $today)
            ->distinct('notification_id')
            ->count('notification_id');
        $clicksWeek  = NotificationClick::query()
            ->where('clicked_at', '>=', $week)
            ->distinct('notification_id')
            ->count('notification_id');

        $ctrToday = $sentToday > 0 ? round(($clicksToday / $sentToday) * 100, 1) : null;
        $ctrWeek  = $sentWeek  > 0 ? round(($clicksWeek  / $sentWeek)  * 100, 1) : null;

        $topClicked = NotificationClick::query()
            ->where('clicked_at', '>=', $week)
            ->whereNotNull('code')
            ->selectRaw('code, COUNT(*) as clicks')
            ->groupBy('code')
            ->orderByDesc('clicks')
            ->limit(5)
            ->get();

        return response()->json(['data' => [
            'devices_total'      => PushSubscription::query()->where('is_active', true)->count(),
            'users_with_push'    => PushSubscription::query()->where('is_active', true)->distinct('user_id')->count('user_id'),
            'devices_inactive'   => PushSubscription::query()->where('is_active', false)->count(),
            'devices_seen_24h'   => PushSubscription::query()->where('last_seen_at', '>=', $today)->count(),
            'devices_seen_week'  => PushSubscription::query()->where('last_seen_at', '>=', $week)->count(),
            'by_platform'        => PushSubscription::query()
                ->where('is_active', true)
                ->selectRaw('COALESCE(platform, "unknown") as platform, COUNT(*) as count')
                ->groupBy('platform')
                ->pluck('count', 'platform'),
            'by_browser'         => PushSubscription::query()
                ->where('is_active', true)
                ->selectRaw('COALESCE(browser, "unknown") as browser, COUNT(*) as count')
                ->groupBy('browser')
                ->pluck('count', 'browser'),

            // Click-through stats
            'sent_today'         => $sentToday,
            'sent_week'          => $sentWeek,
            'clicks_today'       => $clicksToday,
            'clicks_week'        => $clicksWeek,
            'ctr_today_pct'      => $ctrToday,
            'ctr_week_pct'       => $ctrWeek,
            'top_clicked'        => $topClicked,
        ]]);
    }

    /**
     * POST /api/push/click
     *
     * Beacon endpoint hit by the Service Worker's notificationclick
     * handler. Records the interaction without blocking the SW thread —
     * the SW uses fetch(..., { keepalive: true }) so the request
     * survives the tab transition.
     *
     * Public (no auth middleware) because the SW may run without a
     * fresh CSRF token in scope. Identity is reconciled via the
     * supplied endpoint (every push_subscriptions row is owned).
     */
    public function click(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notification_id' => ['nullable', 'integer'],
            'code'            => ['nullable', 'string', 'max:64'],
            'action'          => ['nullable', 'string', 'max:80'],
            'endpoint'        => ['nullable', 'string', 'max:500'],
            'dismissed'       => ['nullable', 'boolean'],
        ]);

        // Reconcile identity from the endpoint when present; fall back
        // to the currently authenticated user if any.
        $userId = $request->user()?->id;
        $subscriptionId = null;
        if (!empty($data['endpoint'])) {
            $sub = PushSubscription::query()->where('endpoint', $data['endpoint'])->first();
            if ($sub) {
                $userId         = $userId ?: $sub->user_id;
                $subscriptionId = $sub->id;
                $sub->markDelivered();   // proves the device is alive
            }
        }

        NotificationClick::query()->create([
            'notification_id' => $data['notification_id'] ?? null,
            'user_id'         => $userId,
            'subscription_id' => $subscriptionId,
            'code'            => $data['code'] ?? null,
            'action'          => $data['action'] ?? null,
            'dismissed'       => (bool) ($data['dismissed'] ?? false),
            'clicked_at'      => now(),
        ]);

        // Auto-mark the underlying notification as read on click.
        if (!empty($data['notification_id'])) {
            SmartNotification::query()
                ->whereKey($data['notification_id'])
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    private function shape(PushSubscription $sub): array
    {
        return [
            'id'            => $sub->id,
            'browser'       => $sub->browser,
            'platform'      => $sub->platform,
            'device_type'   => $sub->device_type,
            'label'         => $sub->label,
            'branch_id'     => $sub->branch_id,
            'last_seen_at'  => optional($sub->last_seen_at)->toIso8601String(),
            'created_at'    => optional($sub->created_at)->toIso8601String(),
            'failure_count' => $sub->failure_count,
        ];
    }
}
