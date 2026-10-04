<?php

namespace Modules\Subscriptions\Events;

/** She paid, but the subscription could not be started. An operator must put it right. */
class PurchaseNeedsAttention extends PurchaseEvent
{
    public function name(): string
    {
        return 'purchase.needs_attention';
    }
}
