<?php

/*
 * Trips settings. Read them as config('trips.ping_interval_seconds').
 */
return [
    // How often a crew phone sends its position while a trip is running. The app is told this number.
    'ping_interval_seconds' => 15,

    // The van counts as "at a stop" when it is this close to the stop's pin.
    'stop_radius_metres' => 150,

    // A position older than this is shown as "last seen", not as live.
    'fresh_for_seconds' => 90,

    // Location history is personal data about the driver and the mate. It is deleted after this many days.
    // Not decided by the business yet. 30 is a placeholder.
    'location_history_days' => (int) env('TRIPS_LOCATION_HISTORY_DAYS', 30),
];
