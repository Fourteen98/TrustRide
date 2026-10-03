<?php

/*
 * Settings that belong to TrustRide as a business, not to one module.
 */
return [
    // Shown in the app wherever a rider is told to get in touch, and until payment in the app exists.
    'support' => [
        'phone' => env('SUPPORT_PHONE'),
        'whatsapp' => env('SUPPORT_WHATSAPP'),
    ],
];
