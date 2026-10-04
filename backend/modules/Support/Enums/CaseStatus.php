<?php

namespace Modules\Support\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
