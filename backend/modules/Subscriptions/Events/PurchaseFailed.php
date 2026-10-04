<?php

namespace Modules\Subscriptions\Events;

/** She was prompted and the money did not come: she declined, had too little, or let it time out. */
class PurchaseFailed extends PurchaseEvent
{
    public function name(): string
    {
        return 'purchase.failed';
    }
}
