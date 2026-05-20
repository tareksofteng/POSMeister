<?php

namespace App\Modules\OMS\Controllers;

use App\Modules\OMS\Models\AppNotification;
use App\Modules\OMS\Models\NotificationTemplate;
use App\Modules\OMS\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    // Inbox
    public function index(Request $request): JsonResponse
    {
        $q = AppNotification::query()
            ->with('template:id,code,name')
            ->orderByDesc('id');

        if ($channel = $request->input('channel')) $q->where('channel', $channel);
        if ($status  = $request->input('status'))  $q->where('status', $status);
        if ($request->boolean('mine')) {
            $q->where('recipient_type', 'user')->where('recipient_id', Auth::id());
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel'        => 'required|in:sms,whatsapp,email,in_app',
            'recipient_type' => 'required|in:customer,user,supplier',
            'recipient_id'   => 'required|integer',
            'recipient_address' => 'nullable|string|max:200',
            'subject'        => 'nullable|string|max:200',
            'body'           => 'required|string',
            'template_code'  => 'nullable|string|max:64',
            'payload'        => 'nullable|array',
            'reference_type' => 'nullable|string|max:32',
            'reference_id'   => 'nullable|integer',
        ]);

        $notification = $this->notifications->queue(
            channel: $data['channel'],
            recipientType: $data['recipient_type'],
            recipientId: (int) $data['recipient_id'],
            body: $data['body'],
            subject: $data['subject'] ?? null,
            templateCode: $data['template_code'] ?? null,
            payload: $data['payload'] ?? [],
            recipientAddress: $data['recipient_address'] ?? null,
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
        );

        return response()->json(['data' => $notification], 201);
    }

    public function markRead(AppNotification $notification): JsonResponse
    {
        return response()->json(['data' => $this->notifications->markRead($notification)]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = AppNotification::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', Auth::id())
            ->where('status', '!=', 'read')
            ->count();
        return response()->json(['data' => ['count' => $count]]);
    }

    // Templates
    public function templates(): JsonResponse
    {
        return response()->json(['data' => NotificationTemplate::orderBy('name')->get()]);
    }

    public function saveTemplate(Request $request, ?NotificationTemplate $template = null): JsonResponse
    {
        $data = $request->validate([
            'code'      => 'required|string|max:64|unique:notification_templates,code,' . ($template?->id ?? 'NULL'),
            'name'      => 'required|string|max:150',
            'channel'   => 'required|in:sms,whatsapp,email,in_app',
            'subject'   => 'nullable|string|max:200',
            'body'      => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);
        $row = $template ?: new NotificationTemplate();
        $row->fill($data);
        $row->save();
        return response()->json(['data' => $row], $template ? 200 : 201);
    }

    public function deleteTemplate(NotificationTemplate $template): JsonResponse
    {
        $template->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
