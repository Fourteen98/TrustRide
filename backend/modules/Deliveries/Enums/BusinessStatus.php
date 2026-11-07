<?php

namespace Modules\Deliveries\Enums;

enum BusinessStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
