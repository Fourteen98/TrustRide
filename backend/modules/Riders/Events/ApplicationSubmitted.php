<?php

namespace Modules\Riders\Events;

class ApplicationSubmitted extends ApplicationEvent
{
    public function name(): string
    {
        return 'application.submitted';
    }
}
