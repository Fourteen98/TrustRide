<?php

namespace Modules\Subscriptions\Events;

class SubscriptionExpiring extends SubscriptionEvent
{
    public function name(): string
    {
        return 'subscription.expiring';
    }
}
