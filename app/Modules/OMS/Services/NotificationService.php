<?php

namespace App\Modules\OMS\Services;

use App\Modules\OMS\Models\AppNotification;
use App\Modules\OMS\Models\NotificationTemplate;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Notification dispatcher. Today this only persists the message and flips
 * its status to `queued`. A future channel worker (SmsGateway, MailGateway,
 * WhatsAppGateway) picks up rows in that state and actually sends them.
 *
 * The split keeps the API stable: callers always go through `queue()` and
 * never know whether the channel is live or stubbed.
 */
class NotificationService
{
    public function queue(
        string $channel,
        string $recipientType,
        int $recipientId,
        string $body,
        ?string $subject = null,
        ?string $templateCode = null,
        array $payload = [],
        ?string $recipientAddress = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        $template = $templateCode
            ? NotificationTemplate::where('code', $templateCode)->where('is_active', true)->first()
            : null;

        if ($template) {
            $body    = $this->render($template->body, $payload);
            $subject = $subject ?: $this->render($template->subject ?? '', $payload);
        }

        return AppNotification::create([
            'template_id'       => $template?->id,
            'channel'           => $channel,
            'recipient_type'    => $recipientType,
            'recipient_id'      => $recipientId,
            'recipient_address' => $recipientAddress,
            'subject'           => $subject,
            'body'              => $body,
            'payload'           => $payload ?: null,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'status'            => 'queued',
            'created_by'        => Auth::id(),
        ]);
    }

    /**
     * Resolve a template by code and queue it. Convenience wrapper.
     */
    public function queueTemplated(string $code, string $recipientType, int $recipientId, array $payload, ?string $recipientAddress = null, ?string $referenceType = null, ?int $referenceId = null): AppNotification
    {
        $template = NotificationTemplate::where('code', $code)->where('is_active', true)->first();
        if (!$template) {
            throw new RuntimeException("Notification template '{$code}' not found or inactive.");
        }
        return $this->queue(
            channel: $template->channel,
            recipientType: $recipientType,
            recipientId: $recipientId,
            body: $template->body,
            subject: $template->subject,
            templateCode: $code,
            payload: $payload,
            recipientAddress: $recipientAddress,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    public function markRead(AppNotification $n): AppNotification
    {
        $n->update(['status' => 'read', 'read_at' => now()]);
        return $n;
    }

    public function markSent(AppNotification $n): AppNotification
    {
        $n->update(['status' => 'sent', 'sent_at' => now()]);
        return $n;
    }

    public function markFailed(AppNotification $n, string $error): AppNotification
    {
        $n->update([
            'status'     => 'failed',
            'last_error' => $error,
            'attempts'   => $n->attempts + 1,
        ]);
        return $n;
    }

    /**
     * Lightweight {{var}} interpolation. Channel adapters can do richer
     * rendering (HTML email, etc.) when they pick up the row.
     */
    private function render(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([\w\.]+)\s*\}\}/', function ($m) use ($vars) {
            return (string) ($vars[$m[1]] ?? '');
        }, $template);
    }
}
