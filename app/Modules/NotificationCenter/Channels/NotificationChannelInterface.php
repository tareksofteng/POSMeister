<?php

namespace App\Modules\NotificationCenter\Channels;

use App\Modules\NotificationCenter\Models\SmartNotification;

/*
 * Every outbound delivery channel implements this contract. The
 * notification engine doesn't know — or care — whether a given alert
 * went out via Web Push, FCM, e-mail, SMS or WhatsApp. That isolation
 * is what keeps the Phase AB engine portable across Phase AD/AE/AF.
 *
 *   PushChannel       — Web Push (this round)
 *   FcmChannel        — Firebase Cloud Messaging (Phase AE)
 *   EmailChannel      — transactional e-mail (Phase AE)
 *   WhatsAppChannel   — WhatsApp Business API (Phase AF)
 *
 * Channels MUST be silent on failure: a downed e-mail provider can
 * never poison the in-app inbox. Return value reports outcome for
 * analytics, exceptions are logged via report() and swallowed.
 */
interface NotificationChannelInterface
{
    /**
     * Identifier used in the analytics + preference UI.
     * e.g. 'web_push', 'fcm', 'email', 'sms', 'whatsapp'.
     */
    public function code(): string;

    /**
     * Is this channel ready to ship? A push channel without VAPID keys
     * returns false; an SMS channel without a provider configured
     * returns false; the engine then skips it without warning.
     */
    public function isReady(): bool;

    /**
     * Deliver a single notification. The recipient resolution (which
     * device, which phone, which inbox) is the channel's responsibility.
     *
     * Returns: [
     *     'attempted' => int,
     *     'delivered' => int,
     *     'failed'    => int,
     *     'skipped'   => int,
     * ]
     */
    public function deliver(SmartNotification $notification): array;
}
