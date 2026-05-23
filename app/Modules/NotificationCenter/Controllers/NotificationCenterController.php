<?php

namespace App\Modules\NotificationCenter\Controllers;

use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Models\SmartNotification;
use App\Modules\NotificationCenter\Services\BusinessEventDetector;
use App\Modules\NotificationCenter\Services\NotificationDigestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly BusinessEventDetector     $detector,
        private readonly NotificationDigestService $digests,
    ) {}

    /** GET /api/notifications — list for current user */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = SmartNotification::query()->active()->forUser($user->id, $user->role ?? null);

        if ($cat = $request->input('category')) $q->where('category', $cat);
        if ($sev = $request->input('severity')) $q->where('severity', $sev);
        if ($request->boolean('unread'))        $q->whereNull('read_at');

        $rows = $q->orderByDesc('urgency')->orderByDesc('id')->limit(100)->get();
        $unread = SmartNotification::query()->active()->forUser($user->id, $user->role ?? null)->unread()->count();

        return response()->json([
            'data'   => $rows,
            'unread' => $unread,
        ]);
    }

    /** POST /api/notifications/mark-all-read */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $affected = SmartNotification::query()
            ->active()
            ->forUser($user->id, $user->role ?? null)
            ->unread()
            ->update(['read_at' => now()]);
        return response()->json(['data' => ['updated' => $affected]]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(SmartNotification $notification): JsonResponse
    {
        if (!$notification->read_at) $notification->update(['read_at' => now()]);
        return response()->json(['data' => $notification]);
    }

    /** POST /api/notifications/{id}/ack */
    public function ack(Request $request, SmartNotification $notification): JsonResponse
    {
        $notification->update([
            'acked_at' => now(),
            'acked_by' => $request->user()->id,
            'read_at'  => $notification->read_at ?? now(),
        ]);
        return response()->json(['data' => $notification]);
    }

    /** POST /api/notifications/{id}/archive */
    public function archive(SmartNotification $notification): JsonResponse
    {
        $notification->update(['archived_at' => now()]);
        return response()->json(['data' => $notification]);
    }

    /** GET /api/notifications/digest */
    public function digest(Request $request): JsonResponse
    {
        $user = $request->user();
        $row = \App\Modules\NotificationCenter\Models\NotificationDigest::query()
            ->where('user_id', $user->id)
            ->where('period', 'daily')
            ->orderByDesc('for_date')
            ->first();
        return response()->json(['data' => $row]);
    }

    /** GET /api/notifications/analytics — admin */
    public function analytics(): JsonResponse
    {
        $base = SmartNotification::query();
        return response()->json(['data' => [
            'unresolved'  => (clone $base)->whereNull('acked_at')->whereNull('archived_at')->count(),
            'critical'    => (clone $base)->whereNull('acked_at')->where('severity', 'critical')->count(),
            'last_24h'    => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
            'last_7d'     => (clone $base)->where('created_at', '>=', now()->subWeek())->count(),
            'by_category' => (clone $base)->where('created_at', '>=', now()->subWeek())
                ->selectRaw('category, COUNT(*) as count')->groupBy('category')->pluck('count', 'category'),
            'top_codes'   => (clone $base)->where('created_at', '>=', now()->subWeek())
                ->selectRaw('code, COUNT(*) as count')->groupBy('code')->orderByDesc('count')->limit(10)->get(),
        ]]);
    }

    /** GET /api/notifications/preferences */
    public function preferences(Request $request): JsonResponse
    {
        $row = NotificationPreference::query()->where('user_id', $request->user()->id)->first();
        $data = $row ? $row->toArray() : array_merge(['user_id' => $request->user()->id], NotificationPreference::defaults());
        return response()->json(['data' => $data]);
    }

    /** PUT /api/notifications/preferences */
    public function savePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'muted_categories'   => ['nullable', 'array'],
            'muted_categories.*' => ['string'],
            'min_severity'       => ['nullable', 'in:info,success,warning,danger,critical'],
            'quiet_hours'        => ['nullable', 'array'],
            'channels'           => ['nullable', 'array'],
            'digest'             => ['nullable', 'array'],
        ]);
        $row = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );
        return response()->json(['data' => $row]);
    }

    /** POST /api/notifications/detect — manual trigger for admins */
    public function runDetectors(): JsonResponse
    {
        return response()->json(['data' => $this->detector->detectAll()]);
    }

    /** POST /api/notifications/build-digest — manual trigger for admins */
    public function buildDigest(): JsonResponse
    {
        $built = $this->digests->buildDaily();
        return response()->json(['data' => ['built' => $built]]);
    }
}
