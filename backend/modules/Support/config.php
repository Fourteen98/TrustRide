<?php

/*
 * Support settings. Read them as config('support.alert_phone').
 */
return [
    // The operator's phone that is texted when an incident is raised. Empty means nobody is texted.
    'alert_phone' => env('SUPPORT_ALERT_PHONE'),

    // Resolved cases older than this are deleted. Incidents are kept.
    'case_retention_days' => (int) env('SUPPORT_CASE_RETENTION_DAYS', 365),

    // How long a resolved case stays visible to the person in the app.
    'show_resolved_days' => 7,
];
