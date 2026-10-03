<?php

/*
 * Subscriptions settings. Read them as config('subscriptions.seat_grace_days').
 * These values were chosen because nothing was decided. Change them here.
 */
return [
    // How many days a seat is kept after a subscription ends. After that it is free for someone else.
    'seat_grace_days' => (int) env('SEAT_GRACE_DAYS', 3),

    // How many days an approved rider has to pay before her reserved seat is given up.
    'reservation_days' => (int) env('SEAT_RESERVATION_DAYS', 7),

    // How many days before the end a rider is told her subscription is running out.
    'expiring_notice_days' => (int) env('EXPIRING_NOTICE_DAYS', 3),
];
