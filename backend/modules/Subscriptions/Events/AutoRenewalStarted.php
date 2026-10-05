<?php

namespace Modules\Subscriptions\Events;

/** She approved direct debit. From now on her plan renews by itself. */
class AutoRenewalStarted extends AutoRenewalEvent
{
    public function name(): string
    {
        return 'auto_renewal.started';
    }
}
