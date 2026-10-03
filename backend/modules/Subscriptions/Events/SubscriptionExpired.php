<?php

namespace Modules\Subscriptions\Events;

class SubscriptionExpired extends SubscriptionEvent
{
    public function name(): string
    {
        return 'subscription.expired';
    }
}
