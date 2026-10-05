<?php

namespace Modules\Payments\Events;

class MandateFailed extends MandateEvent
{
    public function name(): string
    {
        return 'mandate.failed';
    }
}
