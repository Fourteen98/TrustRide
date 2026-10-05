<?php

namespace Modules\Subscriptions\Events;

/** We turned automatic renewal off, because charges kept failing. Not fired when she stops it herself. */
class AutoRenewalStopped extends AutoRenewalEvent
{
    public function name(): string
    {
        return 'auto_renewal.stopped';
    }
}
