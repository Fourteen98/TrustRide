<?php

namespace Modules\Support\Events;

/** Staff answered. The person is told, so she sees it even with the app closed. */
class CaseReplied extends CaseEvent
{
    public function name(): string
    {
        return 'support.replied';
    }

    public function recipients(): array
    {
        return array_filter([$this->person]);
    }
}
