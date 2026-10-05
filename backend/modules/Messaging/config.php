<?php

/*
 * Messaging settings. Read them as config('messaging.channels.sms.driver').
 */
return [
    /*
     * How each channel actually sends.
     *
     *   log    writes the message to storage/logs/laravel.log. Nothing leaves the building.
     *   hubtel real SMS through Hubtel. SMS channel only. Needs the hubtel_sms settings below.
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
     * Hubtel SMS. These are the SMS API keys from the Hubtel dashboard ("SMS API Keys" tab),
     * not the payment keys. The sender id must be one registered under that key. .env only, never git.
     */
    'hubtel_sms' => [
        'client_id' => env('HUBTEL_SMS_CLIENT_ID'),
        'client_secret' => env('HUBTEL_SMS_CLIENT_SECRET'),
        'sender_id' => env('HUBTEL_SMS_SENDER_ID', 'TrustRide'),
        'endpoint' => env('HUBTEL_SMS_ENDPOINT', 'https://sms.hubtel.com/v1/messages/send'),
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
        'purchase.failed' => 'A rider\'s payment for a plan did not go through',
        'purchase.needs_attention' => 'A rider paid, but her subscription could not be started',
        'payment.succeeded' => 'A payment arrived. Has nobody to message. The receipt is subscription.activated.',
        'payment.failed' => 'A payment failed. Has nobody to message. Use purchase.failed.',
        'support.case_opened' => 'Someone asked for help. Has nobody to message.',
        'support.replied' => 'Staff replied to someone who asked for help',
        'support.resolved' => 'A support case was closed',
        'incident.raised' => 'A case became an incident. Goes to the operator on call.',
        'trip.started' => 'The van set off. Goes to everyone riding that day.',
        'trip.ended' => 'The van arrived. Has nobody to message.',
        'rider.picked_up' => 'The mate marked a rider as on board',
        'rider.missed' => 'The mate marked a rider as not at her stop',
        'run.announcement' => 'An operator messaged every rider on a run',
        'driver.approved' => 'A driver passed vetting',
        'driver.statement_paid' => 'A driver was paid for a month',
    ],
];
