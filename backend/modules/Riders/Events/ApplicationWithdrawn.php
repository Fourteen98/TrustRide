<?php

namespace Modules\Riders\Events;

class ApplicationWithdrawn extends ApplicationEvent
{
    public function name(): string
    {
        return 'application.withdrawn';
    }
}
