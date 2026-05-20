<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\Campaign;
use App\Modules\CRM\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $service) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $branch = Auth::user()?->role === 'admin' ? null : Auth::user()?->branch_id;
        return response()->json(['data' => $this->service->list($status, $branch)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'type'            => 'required|in:birthday,comeback,sms,whatsapp,email',
            'message_body'    => 'nullable|string',
            'audience_filter' => 'nullable|array',
            'settings'        => 'nullable|array',
            'scheduled_at'    => 'nullable|date',
            'branch_id'       => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json(['data' => $this->service->create($data)], 201);
    }

    public function update(Campaign $campaign, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:150',
            'message_body'    => 'nullable|string',
            'audience_filter' => 'nullable|array',
            'settings'        => 'nullable|array',
            'scheduled_at'    => 'nullable|date',
        ]);
        return response()->json(['data' => $this->service->update($campaign, $data)]);
    }

    public function schedule(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => $this->service->schedule($campaign)]);
    }

    public function queueDispatch(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => $this->service->queueForDispatch($campaign)]);
    }

    public function cancel(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => $this->service->cancel($campaign)]);
    }

    public function preview(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => $this->service->preview($campaign->id)]);
    }
}
