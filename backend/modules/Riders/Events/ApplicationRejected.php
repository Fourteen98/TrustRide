<?php

namespace Modules\Riders\Events;

use Modules\Riders\Models\RiderApplication;

class ApplicationRejected extends ApplicationEvent
{
    public function __construct(RiderApplication $application)
    {
        parent::__construct($application);

        $this->data['reason'] = $application->rejection_reason?->label();
        $this->data['note'] = $application->rejection_note;
    }

    public function name(): string
    {
        return 'application.rejected';
    }
}
