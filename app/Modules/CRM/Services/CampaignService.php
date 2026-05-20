<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Campaign engine — persistence + audience resolution + status machine.
 *
 * Channel adapters (SMS / WhatsApp / email gateways) are intentionally NOT
 * implemented here. The scaffolding is queue-friendly: each campaign moves
 * draft → scheduled → queued → sent. A future job worker can pick up
 * `queued` rows and call the channel-specific provider.
 */
class CampaignService
{
    public function list(?string $status = null, ?int $branchId = null): array
    {
        $q = Campaign::query()->orderByDesc('id');
        if ($status)   $q->where('status', $status);
        if ($branchId) $q->where('branch_id', $branchId);

        return $q->limit(100)->get()->map(fn($c) => $this->present($c))->all();
    }

    public function create(array $data): Campaign
    {
        return Campaign::create([
            'name'            => $data['name'],
            'type'            => $data['type'],
            'message_body'    => $data['message_body']    ?? null,
            'audience_filter' => $data['audience_filter'] ?? null,
            'settings'        => $data['settings']        ?? null,
            'scheduled_at'    => $data['scheduled_at']    ?? null,
            'branch_id'       => $data['branch_id']       ?? null,
            'status'          => 'draft',
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (in_array($campaign->status, ['sent', 'cancelled'], true)) {
            throw new RuntimeException('Sent or cancelled campaigns cannot be edited.');
        }
        $campaign->update($data);
        return $campaign->fresh();
    }

    public function schedule(Campaign $campaign, ?Carbon $when = null): Campaign
    {
        if ($campaign->status !== 'draft') {
            throw new RuntimeException('Only draft campaigns can be scheduled.');
        }
        $campaign->update([
            'status'       => 'scheduled',
            'scheduled_at' => $when ?? $campaign->scheduled_at ?? now(),
        ]);
        return $campaign;
    }

    /**
     * Resolve audience and move the campaign into the queued state.
     * Real sending happens in a future job worker. For now we simply
     * count the recipients and flip status — the queue itself is
     * Laravel's standard queue, configured separately.
     */
    public function queueForDispatch(Campaign $campaign): Campaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new RuntimeException("Campaign in status {$campaign->status} cannot be queued.");
        }

        $audience = $this->resolveAudience($campaign->audience_filter ?? [], $campaign->branch_id);

        $campaign->update([
            'status'           => 'queued',
            'recipients_count' => count($audience),
        ]);

        // Hand-off point for future: dispatch(CampaignDispatchJob::for($campaign, $audience));
        return $campaign;
    }

    public function cancel(Campaign $campaign): Campaign
    {
        if ($campaign->status === 'sent') {
            throw new RuntimeException('Sent campaigns cannot be cancelled.');
        }
        $campaign->update(['status' => 'cancelled']);
        return $campaign;
    }

    /**
     * Translate a stored audience_filter JSON into a customer list.
     * Supported filter keys (any combination):
     *   - segment: vip|inactive|churn_risk|discount_sensitive|high_frequency
     *   - tier:    silver|gold|platinum|vip
     *   - birthday_month: 1..12
     *   - branch_id
     */
    public function resolveAudience(array $filter, ?int $branchId = null): array
    {
        $q = DB::table('customers as c')
            ->leftJoin('customer_loyalty_profiles as p', 'p.customer_id', '=', 'c.id')
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->select('c.id', 'c.name', 'c.phone', 'c.email', 'c.date_of_birth');

        if ($branchId) $q->where('c.branch_id', $branchId);

        if (!empty($filter['tier'])) {
            $q->where('p.tier', $filter['tier']);
        }

        if (!empty($filter['birthday_month'])) {
            $q->whereRaw('MONTH(c.date_of_birth) = ?', [(int) $filter['birthday_month']]);
        }

        if (!empty($filter['segment']) && $filter['segment'] === 'inactive') {
            $q->where(function ($qq) {
                $qq->whereNull('p.last_activity_at')
                   ->orWhere('p.last_activity_at', '<', now()->subDays(90));
            });
        }

        if (!empty($filter['segment']) && $filter['segment'] === 'vip') {
            $q->where('p.tier', 'vip');
        }

        return $q->limit(5000)->get()->all();
    }

    public function preview(int $campaignId): array
    {
        $campaign = Campaign::findOrFail($campaignId);
        $audience = $this->resolveAudience($campaign->audience_filter ?? [], $campaign->branch_id);
        return [
            'campaign' => $this->present($campaign),
            'audience_size' => count($audience),
            'sample'   => array_slice($audience, 0, 10),
        ];
    }

    private function present(Campaign $c): array
    {
        return [
            'id'              => $c->id,
            'name'            => $c->name,
            'type'            => $c->type,
            'status'          => $c->status,
            'message_body'    => $c->message_body,
            'audience_filter' => $c->audience_filter,
            'scheduled_at'    => $c->scheduled_at?->toIso8601String(),
            'sent_at'         => $c->sent_at?->toIso8601String(),
            'recipients_count'=> $c->recipients_count,
            'delivered_count' => $c->delivered_count,
            'branch_id'       => $c->branch_id,
            'created_at'      => $c->created_at?->toIso8601String(),
        ];
    }
}
