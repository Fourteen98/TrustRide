<?php

namespace Modules\Riders\Events;

class ApplicationApproved extends ApplicationEvent
{
    public function name(): string
    {
        return 'application.approved';
    }
}
