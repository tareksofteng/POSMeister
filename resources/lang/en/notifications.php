<?php

/*
 |--------------------------------------------------------------------------
 | Backend notification copy
 |--------------------------------------------------------------------------
 |
 | Strings consumed by SmartNotificationService::push() via __() so the
 | locale picked by SetLocaleMiddleware applies to the title + message
 | the user sees on the notification bell.
 */
return [
    'serials' => [
        'duplicate' => [
            'title'   => 'Duplicate serial attempt',
            'message' => 'Serial number ":sn" is already registered. The attempted entry was rejected.',
        ],
        'lowStock' => [
            'title'   => 'Low stock — :name (:available left)',
            'message' => 'Serialized stock for :name (:sku) at this branch is at :available units (reorder level: :threshold). Plan a new purchase.',
        ],
        'damagedReturn' => [
            'title'   => 'Damaged serial returned',
            'message' => 'Serial :sn was returned by a customer and marked as damaged. Inspect the device and decide on disposal or repair.',
        ],
    ],
];
