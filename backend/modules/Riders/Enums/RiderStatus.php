<?php

namespace Modules\Riders\Enums;

enum RiderStatus: string
{
    case Applicant = 'applicant';
    case Approved = 'approved';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
