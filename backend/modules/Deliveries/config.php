<?php

/*
 * Aveo Ox: parcel delivery for businesses, in batches by zone.
 *
 * Numbers marked "not decided" are placeholders until the business picks them.
 */
return [
    // Digits in the code the customer gives the driver at the door.
    'code_digits' => 4,

    // Wrong codes allowed before the driver must call the customer or Aveo instead.
    'code_attempts' => 5,

    // What the live map add-on costs a business a month, in pesewas. Not decided (the brief's +GHS 50 is illustrative).
    'live_map_fee' => (int) env('OX_LIVE_MAP_FEE', 5000),

    // What a driver is paid for a run unless the operator says otherwise, in pesewas. Not decided.
    'default_run_pay' => (int) env('OX_DEFAULT_RUN_PAY', 10000),

    // Days a customer's name, phone and address are kept after her parcel is done with. Not decided.
    'customer_data_days' => (int) env('OX_CUSTOMER_DATA_DAYS', 90),
];
