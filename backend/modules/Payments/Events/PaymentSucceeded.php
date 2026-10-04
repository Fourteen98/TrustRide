<?php

namespace Modules\Payments\Events;

class PaymentSucceeded extends PaymentEvent
{
    public function name(): string
    {
        return 'payment.succeeded';
    }
}
