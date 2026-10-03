<?php

namespace Modules\Subscriptions\Events;

class SubscriptionActivated extends SubscriptionEvent
{
    public function name(): string
    {
        return 'subscription.activated';
    }
}
