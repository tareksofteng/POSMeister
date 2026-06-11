<?php

/*
 * Web Push (Phase AD) configuration. Keys are generated with
 *   php artisan push:vapid
 * which writes VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY into .env. Subject
 * is the mailto: contact that browser vendors reach for if our server
 * starts sending malformed pushes.
 */
return [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject'     => env('VAPID_SUBJECT', 'mailto:admin@posmaster.tareksofteng.com'),

    // TTL in seconds the browser will hold the message if the device is
    // offline. 24h is sane for business alerts — older context is rarely
    // actionable.
    'ttl'         => env('PUSH_TTL', 86400),
];
