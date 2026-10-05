<?php

namespace Modules\Subscriptions\Events;

/** Her plan ends in a few days and will be charged by itself. Sent instead of "subscription expiring". */
class AutoRenewalUpcoming extends AutoRenewalEvent
{
    public function name(): string
    {
        return 'auto_renewal.upcoming';
    }
}
