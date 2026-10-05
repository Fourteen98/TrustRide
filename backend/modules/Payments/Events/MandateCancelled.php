<?php

namespace Modules\Payments\Events;

class MandateCancelled extends MandateEvent
{
    public function name(): string
    {
        return 'mandate.cancelled';
    }
}
