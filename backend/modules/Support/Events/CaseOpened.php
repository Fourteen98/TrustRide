<?php

namespace Modules\Support\Events;

/** Someone asked for help. Nobody is messaged by default. The case is waiting in the console. */
class CaseOpened extends CaseEvent
{
    public function name(): string
    {
        return 'support.case_opened';
    }
}
