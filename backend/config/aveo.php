<?php

/*
 * Settings that belong to Aveo as a business, not to one module.
 */
return [
    // Shown in the app wherever a rider is told to get in touch, and until payment in the app exists.
    'support' => [
        'phone' => env('SUPPORT_PHONE'),
        'whatsapp' => env('SUPPORT_WHATSAPP'),
    ],

    'report' => [
        // A trip counts as on time if it started within this many minutes of its departure time.
        'on_time_minutes' => (int) env('REPORT_ON_TIME_MINUTES', 10),
    ],

    'maps' => [
        // A Google Maps Platform key with the Routes API switched on. Server side only: restrict it to the
        // server's IP in Google Cloud. Without it, routes are drawn straight and riders see stops, not minutes.
        'google_key' => env('GOOGLE_MAPS_API_KEY'),

        // Google bills each call. Past this many in a day, nothing more is asked until tomorrow.
        'daily_limit' => (int) env('GOOGLE_MAPS_DAILY_LIMIT', 2000),

        // How long a "minutes away" answer is reused before Google is asked again. Everyone looking at
        // the same van and stop in that time shares one call.
        'eta_cache_seconds' => (int) env('GOOGLE_MAPS_ETA_CACHE_SECONDS', 60),
    ],
];
