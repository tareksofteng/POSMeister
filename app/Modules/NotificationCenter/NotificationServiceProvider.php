<?php

namespace App\Modules\NotificationCenter;

use App\Modules\NotificationCenter\Channels\WebPushChannel;
use App\Modules\NotificationCenter\Services\PushDispatcher;
use Illuminate\Support\ServiceProvider;

/*
 * Wires the channel registry. Bound as a singleton so every service that
 * calls SmartNotificationService gets the same dispatcher instance —
 * critical for the in-process throttle cache to stay consistent inside a
 * single request.
 *
 * To add a new channel (Phase AE / AF):
 *   1. implement NotificationChannelInterface
 *   2. register the class below
 *
 * No other code in the engine needs to know about the new channel.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushDispatcher::class, function ($app) {
            $dispatcher = new PushDispatcher();
            $dispatcher->register($app->make(WebPushChannel::class));
            // Future: EmailChannel, FcmChannel, SmsChannel, WhatsAppChannel
            return $dispatcher;
        });
    }
}
