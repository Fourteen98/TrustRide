<?php

namespace Modules\Payments\Events;

class PaymentFailed extends PaymentEvent
{
    public function name(): string
    {
        return 'payment.failed';
    }
}
