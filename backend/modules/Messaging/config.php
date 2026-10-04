<?php

/*
 * Messaging settings. Read them as config('messaging.channels.sms.driver').
 */
return [
    /*
     * How each channel actually sends.
     *
     *   log    writes the message to storage/logs/laravel.log. Nothing leaves the building.
     *   null   the channel is not set up. Delivery skips it and falls back to the next one.
     *
     * A real provider is one class that implements Modules\Messaging\Channels\Channel,
     * added to ChannelManager::DRIVERS and named here.
     */
    'channels' => [
        'sms' => ['driver' => env('MESSAGING_SMS_DRIVER', 'log')],
        'whatsapp' => ['driver' => env('MESSAGING_WHATSAPP_DRIVER', 'null')],
        'push' => ['driver' => env('MESSAGING_PUSH_DRIVER', 'null')],
    ],

    /*
     * When a rule says "her preferred channel" and that fails or isn't set up,
     * these are tried in this order. SMS is last because it reaches every phone.
     */
    'fallback_order' => ['push', 'whatsapp', 'sms'],

    /*
     * The events modules fire today, so the console can offer them when writing a rule.
     * This list is only a help for the person typing. An event not listed here still works.
     */
    'known_events' => [
        'account.registered' => 'Someone logged in to the app for the first time',
        'application.submitted' => 'A rider applied for a seat',
        'application.approved' => 'An application was approved and a seat reserved',
        'application.rejected' => 'An application was rejected',
        'application.withdrawn' => 'A rider withdrew her application',
        'subscription.activated' => 'A subscription started. The receipt.',
        'subscription.expiring' => 'A subscription ends in a few days',
        'subscription.expired' => 'A subscription has ended',
        'run.announcement' => 'An operator messaged every rider on a run',
        'driver.approved' => 'A driver passed vetting',
        'driver.statement_paid' => 'A driver was paid for a month',
    ],
];
