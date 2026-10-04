<?php

namespace Modules\Support\Events;

class CaseResolved extends CaseEvent
{
    public function name(): string
    {
        return 'support.resolved';
    }

    public function recipients(): array
    {
        return array_filter([$this->person]);
    }
}
