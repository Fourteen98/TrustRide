<?php

/*
 * Support settings. Read them as config('support.alert_phone').
 */
return [
    // The operator's phone that is texted when an incident is raised. Empty means nobody is texted.
    'alert_phone' => env('SUPPORT_ALERT_PHONE'),

    // How long a resolved case stays visible to the person in the app.
    'show_resolved_days' => 7,
];
