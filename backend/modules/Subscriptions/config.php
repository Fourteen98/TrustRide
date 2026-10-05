<?php

/*
 * Subscriptions settings. Read them as config('subscriptions.seat_grace_days').
 * These are the starting values. Staff change them in the console under Subscriptions > Plans > Rules,
 * and a value saved there replaces the one here.
 */
return [
    // How many days a seat is kept after a subscription ends. After that it is free for someone else.
    'seat_grace_days' => (int) env('SEAT_GRACE_DAYS', 3),

    // How many days an approved rider has to pay before her reserved seat is given up.
    'reservation_days' => (int) env('SEAT_RESERVATION_DAYS', 7),

    // How many days before the end a rider is told her subscription is running out.
    'expiring_notice_days' => (int) env('EXPIRING_NOTICE_DAYS', 3),

    // Automatic renewal takes the money this many days before the subscription ends, so a failed
    // charge leaves her time to pay by hand. It is tried once a day until it works or gives up.
    'auto_renew_charge_days_before' => (int) env('AUTO_RENEW_CHARGE_DAYS_BEFORE', 1),

    // Charges in a row that may fail before automatic renewal is switched off and she is told.
    'auto_renew_max_failures' => (int) env('AUTO_RENEW_MAX_FAILURES', 3),
];
