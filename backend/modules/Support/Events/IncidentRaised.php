<?php

namespace Modules\Support\Events;

use App\Support\Recipient;

/** A case became an incident. The operator on call is texted, if a number is set. */
class IncidentRaised extends CaseEvent
{
    public function name(): string
    {
        return 'incident.raised';
    }

    public function recipients(): array
    {
        $phone = config('support.alert_phone');

        return $phone ? [new Recipient('operator', $phone, 'sms', 'Operator on call')] : [];
    }
}
