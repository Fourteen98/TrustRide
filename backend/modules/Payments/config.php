<?php

/*
 * Payments settings. Read them as config('payments.driver').
 *
 * NEVER put real credentials in this file or in git. They go in .env only.
 */
return [
    /*
     * Which gateway takes the money.
     *
     *   fake     No real money. A payment succeeds by itself a few seconds later. For development.
     *            A phone number ending in 0000 fails instead, so the failure path can be tried.
     *   hubtel   Hubtel Direct Receive Money. The rider gets a mobile money prompt on her phone.
     */
    'driver' => env('PAYMENTS_DRIVER', 'fake'),

    /*
     * The fake gateway's direct debit: a mandate approves itself after the same wait.
     * A number ending 0000 is declined. A number ending 1111 asks for a code instead: the code is 1234.
     */
    'fake' => [
        'settle_after_seconds' => (int) env('PAYMENTS_FAKE_SETTLE_SECONDS', 5),
    ],

    'hubtel' => [
        // The basic-auth pair from the Hubtel merchant dashboard.
        'username' => env('HUBTEL_API_USERNAME'),
        'api_key' => env('HUBTEL_API_KEY'),

        // The POS Sales ID (merchant account number) the money settles into.
        'merchant_account_number' => env('HUBTEL_MERCHANT_ACCOUNT_NUMBER'),

        'receive_base_url' => env('HUBTEL_RECEIVE_BASE_URL', 'https://rmp.hubtel.com'),
        // Direct debit mandates ("preapproval").
        'preapproval_base_url' => env('HUBTEL_PREAPPROVAL_BASE_URL', 'https://preapproval.hubtel.com'),
        'preapproval_callback_url' => env('HUBTEL_PREAPPROVAL_CALLBACK_URL'),
        'status_base_url' => env('HUBTEL_STATUS_BASE_URL', 'https://api-txnstatus.hubtel.com'),

        // Where Hubtel POSTs the result. Must be reachable from the internet, so not localhost.
        // Left empty, it is built from APP_URL.
        'callback_url' => env('HUBTEL_CALLBACK_URL'),
    ],

    /*
     * The mobile money networks a rider can pay from. The key is what the app sends.
     * "channel" is the name Hubtel uses for a prompted payment on that network.
     * "debit_channel" is its name for direct debit. Null means the network has no direct debit,
     * and the app then only offers paying once.
     */
    'networks' => [
        'mtn' => ['label' => 'MTN MoMo', 'channel' => 'mtn-gh', 'debit_channel' => 'mtn-gh-direct-debit'],
        'telecel' => ['label' => 'Telecel Cash', 'channel' => 'vodafone-gh', 'debit_channel' => 'vodafone-gh-direct-debit'],
        'airteltigo' => ['label' => 'AirtelTigo Money', 'channel' => 'tigo-gh', 'debit_channel' => null],
    ],

    // How long an unpaid payment is given before a status check may mark it failed.
    'fail_after_minutes' => (int) env('PAYMENTS_FAIL_AFTER_MINUTES', 15),
];
