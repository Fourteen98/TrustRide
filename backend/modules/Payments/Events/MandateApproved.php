<?php

namespace Modules\Payments\Events;

class MandateApproved extends MandateEvent
{
    public function name(): string
    {
        return 'mandate.approved';
    }
}
